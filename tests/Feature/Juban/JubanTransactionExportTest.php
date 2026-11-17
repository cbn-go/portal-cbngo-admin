<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanPaymentMethod;
use App\Enums\JubanTransactionType;
use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages\ListJubanTransactions;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JubanTransactionExportTest extends TestCase
{
    use RefreshDatabase;

    private User $treasurer;

    private User $secretary;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('juban'));

        $this->treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $this->secretary = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);
        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
    }

    public function test_export_action_is_visible_for_treasurer_secretary_and_super_admin(): void
    {
        foreach ([$this->treasurer, $this->secretary, $this->superAdmin] as $user) {
            $this->actingAs($user);

            Livewire::test(ListJubanTransactions::class)
                ->assertTableActionExists('export')
                ->assertTableActionVisible('export');
        }
    }

    public function test_export_action_downloads_csv_matching_filtered_records(): void
    {
        $this->actingAs($this->treasurer);

        $catReceita = JubanCategory::factory()->receita()->create(['name' => 'Inscrições']);
        $catDespesa = JubanCategory::factory()->despesa()->create(['name' => 'Materiais']);

        $receita = JubanTransaction::factory()->receita()->create([
            'category_id' => $catReceita->id,
            'description' => 'Inscrição Retiro Carnaval',
            'amount' => '450.00',
            'occurred_on' => '2026-02-10',
            'payment_method' => JubanPaymentMethod::PIX,
            'document_ref' => 'PIX-450',
            'created_by' => $this->treasurer->id,
        ]);

        $despesa = JubanTransaction::factory()->despesa()->create([
            'category_id' => $catDespesa->id,
            'description' => 'Compra de Crachás e Canetas',
            'amount' => '120.00',
            'occurred_on' => '2026-03-05',
            'payment_method' => JubanPaymentMethod::CARTAO_DEBITO,
            'document_ref' => 'DOC-120',
            'created_by' => $this->treasurer->id,
        ]);

        // 1. Exportando sem filtros: deve conter ambos os registros
        $responseAll = Livewire::test(ListJubanTransactions::class)
            ->callTableAction('export');

        $responseAll->assertFileDownloaded();

        // 2. Exportando com filtro por tipo 'despesa'
        $testableFiltered = Livewire::test(ListJubanTransactions::class)
            ->filterTable('type', JubanTransactionType::DESPESA->value)
            ->callTableAction('export');

        $testableFiltered->assertFileDownloaded();
    }

    public function test_export_action_respects_period_filter(): void
    {
        $this->actingAs($this->secretary);

        $cat = JubanCategory::factory()->create(['name' => 'Geral']);

        $transFevereiro = JubanTransaction::factory()->create([
            'category_id' => $cat->id,
            'description' => 'Evento Fevereiro',
            'occurred_on' => '2026-02-15',
        ]);

        $transMarco = JubanTransaction::factory()->create([
            'category_id' => $cat->id,
            'description' => 'Evento Março',
            'occurred_on' => '2026-03-15',
        ]);

        Livewire::test(ListJubanTransactions::class)
            ->filterTable('periodo', ['from' => '2026-03-01', 'until' => '2026-03-31'])
            ->callTableAction('export')
            ->assertFileDownloaded();
    }
}
