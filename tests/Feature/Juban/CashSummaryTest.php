<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanTransactionType;
use App\Enums\UserRole;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages\ListJubanTransactions;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Services\Juban\CashSummary;
use App\Services\Juban\CashSummaryResult;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class CashSummaryTest extends TestCase
{
    use RefreshDatabase;

    private JubanCategory $ofertas;

    private JubanCategory $inscricoes;

    private JubanCategory $eventos;

    private JubanCategory $viagens;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ofertas = JubanCategory::factory()->receita()->create(['name' => 'Ofertas e Doações']);
        $this->inscricoes = JubanCategory::factory()->receita()->create(['name' => 'Inscrições de Eventos']);
        $this->eventos = JubanCategory::factory()->despesa()->create(['name' => 'Eventos e congressos']);
        $this->viagens = JubanCategory::factory()->despesa()->create(['name' => 'Viagens e deslocamento']);
    }

    private function lancamento(JubanTransactionType $type, JubanCategory $category, string $amount, string $date): JubanTransaction
    {
        return JubanTransaction::factory()->create([
            'type' => $type,
            'category_id' => $category->id,
            'amount' => $amount,
            'occurred_on' => $date,
        ]);
    }

    private function summarize(string $from, string $until): CashSummaryResult
    {
        return app(CashSummary::class)->forPeriod(Carbon::parse($from), Carbon::parse($until));
    }

    public function test_saldo_is_receitas_minus_despesas_with_a_mix_of_transactions(): void
    {
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '1000.00', '2026-03-02');
        $this->lancamento(JubanTransactionType::RECEITA, $this->inscricoes, '250.50', '2026-03-10');
        $this->lancamento(JubanTransactionType::DESPESA, $this->eventos, '480.00', '2026-03-12');
        $this->lancamento(JubanTransactionType::DESPESA, $this->viagens, '70.25', '2026-03-20');

        $summary = $this->summarize('2026-03-01', '2026-03-31');

        $this->assertSame(1250.50, $summary->receitas);
        $this->assertSame(550.25, $summary->despesas);
        $this->assertSame(700.25, $summary->saldo);
        $this->assertSame(4, $summary->count);
    }

    public function test_saldo_can_be_negative(): void
    {
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '100.00', '2026-03-02');
        $this->lancamento(JubanTransactionType::DESPESA, $this->eventos, '350.10', '2026-03-05');

        $this->assertSame(-250.10, $this->summarize('2026-03-01', '2026-03-31')->saldo);
    }

    public function test_period_edges_are_inclusive_and_outside_dates_are_excluded(): void
    {
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '10.00', '2026-03-01');
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '20.00', '2026-03-31');
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '400.00', '2026-02-28');
        $this->lancamento(JubanTransactionType::DESPESA, $this->eventos, '900.00', '2026-04-01');

        $summary = $this->summarize('2026-03-01', '2026-03-31');

        $this->assertSame(30.0, $summary->receitas);
        $this->assertSame(0.0, $summary->despesas);
        $this->assertSame(2, $summary->count);
    }

    public function test_soft_deleted_transactions_are_ignored(): void
    {
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '100.00', '2026-03-02');
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '999.00', '2026-03-03')->delete();

        $summary = $this->summarize('2026-03-01', '2026-03-31');

        $this->assertSame(100.0, $summary->receitas);
        $this->assertSame(1, $summary->count);
    }

    public function test_breakdown_by_category_with_percent_of_each_type(): void
    {
        $ajuste = JubanCategory::factory()->ambos()->create(['name' => 'Ajuste de Saldo']);

        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '300.00', '2026-03-02');
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '450.00', '2026-03-03');
        $this->lancamento(JubanTransactionType::RECEITA, $ajuste, '250.00', '2026-03-04');
        $this->lancamento(JubanTransactionType::DESPESA, $this->eventos, '600.00', '2026-03-05');
        $this->lancamento(JubanTransactionType::DESPESA, $ajuste, '200.00', '2026-03-06');

        $summary = $this->summarize('2026-03-01', '2026-03-31');

        $receitas = $summary->categoriesOf(JubanTransactionType::RECEITA);
        $this->assertSame(['Ofertas e Doações', 'Ajuste de Saldo'], array_map(fn ($c) => $c->name, $receitas));
        $this->assertSame([750.0, 250.0], array_map(fn ($c) => $c->total, $receitas));
        $this->assertSame([75.0, 25.0], array_map(fn ($c) => $c->percentOfType, $receitas));
        $this->assertSame(2, $receitas[0]->count);

        $despesas = $summary->categoriesOf(JubanTransactionType::DESPESA);
        $this->assertSame(['Eventos e congressos', 'Ajuste de Saldo'], array_map(fn ($c) => $c->name, $despesas));
        $this->assertSame([75.0, 25.0], array_map(fn ($c) => $c->percentOfType, $despesas));

        // A soma da quebra fecha com os totais.
        $this->assertSame($summary->receitas, array_sum(array_map(fn ($c) => $c->total, $receitas)));
        $this->assertSame($summary->despesas, array_sum(array_map(fn ($c) => $c->total, $despesas)));
    }

    public function test_totals_match_the_transactions_listed_with_the_same_period_filter(): void
    {
        $this->lancamento(JubanTransactionType::RECEITA, $this->ofertas, '1234.56', '2026-05-03');
        $this->lancamento(JubanTransactionType::DESPESA, $this->viagens, '234.50', '2026-05-18');
        $this->lancamento(JubanTransactionType::DESPESA, $this->eventos, '10.00', '2026-06-01');

        Filament::setCurrentPanel(Filament::getPanel('juban'));
        $this->actingAs(User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]));

        $listed = Livewire::test(ListJubanTransactions::class)
            ->filterTable('periodo', ['from' => '2026-05-01', 'until' => '2026-05-31'])
            ->instance()
            ->getFilteredTableQuery()
            ->get();

        $summary = $this->summarize('2026-05-01', '2026-05-31');

        $this->assertCount($summary->count, $listed);
        $this->assertSame(
            round($listed->sum(fn (JubanTransaction $t): float => $t->signedAmount()), 2),
            $summary->saldo,
        );
    }

    public function test_period_from_filters_defaults_to_current_month_and_orders_dates(): void
    {
        Carbon::setTestNow('2026-11-17 10:00:00');

        [$from, $until] = CashSummary::periodFromFilters(null);
        $this->assertSame('2026-11-01', $from->toDateString());
        $this->assertSame('2026-11-30', $until->toDateString());

        [$from, $until] = CashSummary::periodFromFilters(['from' => '2026-10-31', 'until' => '2026-10-01']);
        $this->assertSame('2026-10-01', $from->toDateString());
        $this->assertSame('2026-10-31', $until->toDateString());

        Carbon::setTestNow();
    }
}
