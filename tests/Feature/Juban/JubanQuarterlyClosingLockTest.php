<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanTransactionResource;
use App\Models\JubanCategory;
use App\Models\JubanReport;
use App\Models\JubanTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JubanQuarterlyClosingLockTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('juban'));
    }

    public function test_cannot_create_transaction_with_occurred_on_in_closed_quarter(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $category = JubanCategory::factory()->receita()->create();

        // Q1 de 2025 fechado
        JubanReport::factory()->create([
            'year' => 2025,
            'quarter' => 1,
            'period_from' => '2025-01-01',
            'period_until' => '2025-03-31',
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $this->actingAs($treasurer);

        // Formulário de criação deve rejeitar data travada
        Livewire::test(JubanTransactionResource\Pages\CreateJubanTransaction::class)
            ->fillForm([
                'type' => 'receita',
                'amount' => '150.00',
                'occurred_on' => '2025-02-15',
                'category_id' => $category->id,
                'description' => 'Tentativa de lançamento em período fechado',
            ])
            ->call('create')
            ->assertHasFormErrors(['occurred_on']);

        $this->assertDatabaseMissing('juban_transactions', [
            'description' => 'Tentativa de lançamento em período fechado',
        ]);
    }

    public function test_cannot_update_transaction_in_closed_quarter(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $category = JubanCategory::factory()->receita()->create();

        $transaction = JubanTransaction::factory()->receita()->create([
            'occurred_on' => '2025-02-10',
            'description' => 'Lançamento original',
            'category_id' => $category->id,
        ]);

        // Fecha o Q1 de 2025
        JubanReport::factory()->create([
            'year' => 2025,
            'quarter' => 1,
            'period_from' => '2025-01-01',
            'period_until' => '2025-03-31',
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $this->actingAs($treasurer);

        // Acesso à tela de edição ou submissão do update deve falhar
        $this->assertFalse($treasurer->can('update', $transaction));

        $this->get(JubanTransactionResource::getUrl('edit', ['record' => $transaction]))
            ->assertForbidden();
    }

    public function test_cannot_delete_transaction_in_closed_quarter(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);

        $transaction = JubanTransaction::factory()->receita()->create([
            'occurred_on' => '2025-02-10',
        ]);

        // Fecha o Q1 de 2025
        JubanReport::factory()->create([
            'year' => 2025,
            'quarter' => 1,
            'period_from' => '2025-01-01',
            'period_until' => '2025-03-31',
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $this->actingAs($treasurer);

        $this->assertFalse($treasurer->can('delete', $transaction));
    }

    public function test_reopened_quarter_allows_transactions_again(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $category = JubanCategory::factory()->receita()->create();

        $transaction = JubanTransaction::factory()->receita()->create([
            'occurred_on' => '2025-02-10',
            'description' => 'Lançamento original',
            'category_id' => $category->id,
        ]);

        // Cria relatório com status REABERTO
        JubanReport::factory()->reaberto()->create([
            'year' => 2025,
            'quarter' => 1,
            'period_from' => '2025-01-01',
            'period_until' => '2025-03-31',
        ]);

        $this->actingAs($treasurer);

        // O período reaberto não está travado
        $this->assertTrue($treasurer->can('update', $transaction));
        $this->assertTrue($treasurer->can('delete', $transaction));

        Livewire::test(JubanTransactionResource\Pages\CreateJubanTransaction::class)
            ->fillForm([
                'type' => 'receita',
                'amount' => '250.00',
                'occurred_on' => '2025-02-20',
                'category_id' => $category->id,
                'description' => 'Lançamento em período reaberto',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('juban_transactions', [
            'description' => 'Lançamento em período reaberto',
        ]);
    }
}
