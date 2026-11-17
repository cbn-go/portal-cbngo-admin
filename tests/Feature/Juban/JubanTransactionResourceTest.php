<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanPaymentMethod;
use App\Enums\JubanTransactionType;
use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanTransactionResource;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages\CreateJubanTransaction;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages\EditJubanTransaction;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages\ListJubanTransactions;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Tables\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class JubanTransactionResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $treasurer;

    private JubanCategory $receitaCategory;

    private JubanCategory $despesaCategory;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('juban'));

        $this->treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $this->receitaCategory = JubanCategory::factory()->receita()->create(['name' => 'Ofertas e Doações']);
        $this->despesaCategory = JubanCategory::factory()->despesa()->create(['name' => 'Eventos e congressos']);

        $this->actingAs($this->treasurer);
    }

    public function test_treasurer_creates_a_receita_with_created_by_filled(): void
    {
        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::RECEITA->value,
                'amount' => '1250.50',
                'occurred_on' => '2026-03-10',
                'category_id' => $this->receitaCategory->id,
                'description' => 'Ofertas do culto de jovens',
                'payment_method' => JubanPaymentMethod::PIX->value,
                'document_ref' => 'PIX-0001',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $transaction = JubanTransaction::query()->sole();

        $this->assertSame(JubanTransactionType::RECEITA, $transaction->type);
        $this->assertSame('1250.50', $transaction->amount);
        $this->assertSame('2026-03-10', $transaction->occurred_on->toDateString());
        $this->assertSame($this->receitaCategory->id, $transaction->category_id);
        $this->assertSame(JubanPaymentMethod::PIX, $transaction->payment_method);
        $this->assertSame('PIX-0001', $transaction->document_ref);
        $this->assertSame($this->treasurer->id, $transaction->created_by);
    }

    public function test_treasurer_creates_a_despesa(): void
    {
        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::DESPESA->value,
                'amount' => '480',
                'occurred_on' => '2026-04-02',
                'category_id' => $this->despesaCategory->id,
                'description' => 'Locação do auditório do congresso',
                'payment_method' => JubanPaymentMethod::BOLETO->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('juban_transactions', [
            'type' => JubanTransactionType::DESPESA->value,
            'category_id' => $this->despesaCategory->id,
            'description' => 'Locação do auditório do congresso',
            'created_by' => $this->treasurer->id,
        ]);
    }

    public function test_category_that_accepts_both_types_is_valid_for_a_despesa(): void
    {
        $ajuste = JubanCategory::factory()->ambos()->create();

        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::DESPESA->value,
                'amount' => '10',
                'occurred_on' => '2026-04-02',
                'category_id' => $ajuste->id,
                'description' => 'Ajuste de conciliação',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('juban_transactions', 1);
    }

    public function test_rejects_a_category_incompatible_with_the_type(): void
    {
        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::RECEITA->value,
                'amount' => '300',
                'occurred_on' => '2026-03-10',
                'category_id' => $this->despesaCategory->id,
                'description' => 'Receita com categoria de despesa',
            ])
            ->call('create')
            ->assertHasFormErrors(['category_id']);

        $this->assertDatabaseCount('juban_transactions', 0);
    }

    public function test_rejects_incompatible_category_on_edit(): void
    {
        $transaction = JubanTransaction::factory()->create([
            'type' => JubanTransactionType::DESPESA,
            'category_id' => $this->despesaCategory->id,
        ]);

        Livewire::test(EditJubanTransaction::class, ['record' => $transaction->getRouteKey()])
            ->fillForm([
                'type' => JubanTransactionType::RECEITA->value,
                'category_id' => $this->despesaCategory->id,
            ])
            ->call('save')
            ->assertHasFormErrors(['category_id']);

        $this->assertSame(JubanTransactionType::DESPESA, $transaction->fresh()?->type);
    }

    public function test_amount_must_be_greater_than_zero_and_date_is_required(): void
    {
        foreach (['0', '-15'] as $amount) {
            Livewire::test(CreateJubanTransaction::class)
                ->fillForm([
                    'type' => JubanTransactionType::DESPESA->value,
                    'amount' => $amount,
                    'occurred_on' => '2026-03-10',
                    'category_id' => $this->despesaCategory->id,
                    'description' => 'Valor inválido',
                ])
                ->call('create')
                ->assertHasFormErrors(['amount']);
        }

        Livewire::test(CreateJubanTransaction::class)
            ->fillForm([
                'type' => JubanTransactionType::DESPESA->value,
                'amount' => '15',
                'occurred_on' => null,
                'category_id' => $this->despesaCategory->id,
                'description' => 'Sem data',
            ])
            ->call('create')
            ->assertHasFormErrors(['occurred_on' => 'required']);

        $this->assertDatabaseCount('juban_transactions', 0);
    }

    public function test_category_options_follow_the_type_and_hide_inactive_ones(): void
    {
        $ambos = JubanCategory::factory()->ambos()->create();
        $inativa = JubanCategory::factory()->receita()->inactive()->create();

        $ids = JubanTransactionResource::categoryOptionsQuery(JubanCategory::query(), JubanTransactionType::RECEITA->value)
            ->pluck('id')
            ->all();

        $this->assertEqualsCanonicalizing([$this->receitaCategory->id, $ambos->id], $ids);
        $this->assertNotContains($inativa->id, $ids);
        $this->assertSame([], JubanTransactionResource::categoryOptionsQuery(JubanCategory::query(), null)->pluck('id')->all());
    }

    public function test_list_filters_by_type_category_and_period(): void
    {
        $marco = JubanTransaction::factory()->create([
            'type' => JubanTransactionType::RECEITA,
            'category_id' => $this->receitaCategory->id,
            'occurred_on' => '2026-03-15',
        ]);
        $abril = JubanTransaction::factory()->create([
            'type' => JubanTransactionType::DESPESA,
            'category_id' => $this->despesaCategory->id,
            'occurred_on' => '2026-04-20',
        ]);

        Livewire::test(ListJubanTransactions::class)
            ->assertCanSeeTableRecords([$marco, $abril])
            ->filterTable('type', JubanTransactionType::DESPESA->value)
            ->assertCanSeeTableRecords([$abril])
            ->assertCanNotSeeTableRecords([$marco]);

        Livewire::test(ListJubanTransactions::class)
            ->filterTable('category_id', $this->receitaCategory->id)
            ->assertCanSeeTableRecords([$marco])
            ->assertCanNotSeeTableRecords([$abril]);

        Livewire::test(ListJubanTransactions::class)
            ->filterTable('periodo', ['from' => '2026-04-01', 'until' => '2026-04-30'])
            ->assertCanSeeTableRecords([$abril])
            ->assertCanNotSeeTableRecords([$marco]);
    }

    public function test_list_searches_by_description_and_formats_amount_in_brl(): void
    {
        $congresso = JubanTransaction::factory()->create([
            'type' => JubanTransactionType::DESPESA,
            'category_id' => $this->despesaCategory->id,
            'description' => 'Congresso estadual da juventude',
            'amount' => '12345.60',
        ]);
        $oferta = JubanTransaction::factory()->create([
            'type' => JubanTransactionType::RECEITA,
            'category_id' => $this->receitaCategory->id,
            'description' => 'Oferta missionária',
        ]);

        Livewire::test(ListJubanTransactions::class)
            ->searchTable('Congresso')
            ->assertCanSeeTableRecords([$congresso])
            ->assertCanNotSeeTableRecords([$oferta])
            ->assertSee('- R$ 12.345,60');
    }

    public function test_secretary_cannot_delete_transactions_but_treasurer_can(): void
    {
        $transaction = JubanTransaction::factory()->receita()->create();

        Livewire::test(ListJubanTransactions::class)
            ->assertTableActionVisible(DeleteAction::class, $transaction);

        $secretary = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);
        $this->actingAs($secretary);

        Livewire::test(ListJubanTransactions::class)
            ->assertTableActionHidden(DeleteAction::class, $transaction);
    }

    public function test_treasurer_soft_deletes_and_record_leaves_the_list(): void
    {
        $transaction = JubanTransaction::factory()->receita()->create();

        Livewire::test(ListJubanTransactions::class)
            ->callTableAction(DeleteAction::class, $transaction)
            ->assertCanNotSeeTableRecords([$transaction]);

        $this->assertSoftDeleted($transaction);
    }
}
