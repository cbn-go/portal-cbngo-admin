<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanTransactionType;
use App\Enums\UserRole;
use App\Filament\Juban\Pages\Dashboard;
use App\Filament\Juban\Widgets\CashByCategoryWidget;
use App\Filament\Juban\Widgets\CashSummaryStatsWidget;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CashSummaryPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-17 10:00:00');
        Filament::setCurrentPanel(Filament::getPanel('juban'));

        $ofertas = JubanCategory::factory()->receita()->create(['name' => 'Ofertas e Doações']);
        $eventos = JubanCategory::factory()->despesa()->create(['name' => 'Eventos e congressos']);

        JubanTransaction::factory()->create(['type' => JubanTransactionType::RECEITA, 'category_id' => $ofertas->id, 'amount' => '1250.50', 'occurred_on' => '2026-11-05']);
        JubanTransaction::factory()->create(['type' => JubanTransactionType::DESPESA, 'category_id' => $eventos->id, 'amount' => '480.00', 'occurred_on' => '2026-10-05']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{UserRole}>
     */
    public static function treasuryRoles(): array
    {
        return [
            'super admin' => [UserRole::SUPER_ADMIN],
            'tesouraria' => [UserRole::JUBAN_TREASURER],
            'secretaria' => [UserRole::CONVENTION_EDITOR],
        ];
    }

    #[DataProvider('treasuryRoles')]
    public function test_panel_home_shows_current_year_summary(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role, 'name' => 'Ana Tesoureira', 'is_active' => true]));

        $this->assertSame(url('/juban'), Dashboard::getUrl());

        // 10h UTC = 7h em Brasília.
        $this->get('/juban')
            ->assertSuccessful()
            ->assertSee('Bom dia, Ana')
            ->assertSee('Resumo de caixa')
            ->assertSee('Novo lançamento')
            ->assertSeeInOrder(['Gastos em 2026', 'R$ 480,00', 'Receita em 2026', 'R$ 1.250,50', 'Saldo em 2026', 'R$ 770,50'])
            ->assertSee('Gastos da JUBAN GO em 2026')
            ->assertSee('01/01/2026 a 31/12/2026')
            ->assertSee('Ofertas e Doações')
            ->assertSee('Eventos e congressos');
    }

    public function test_widgets_follow_the_selected_period(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]));

        $october = ['from' => '2026-10-01', 'until' => '2026-10-31'];

        Livewire::test(CashSummaryStatsWidget::class, ['filters' => $october])
            ->assertSee('Saldo em outubro de 2026')
            ->assertSee('R$ 480,00')
            ->assertSee('- R$ 480,00')
            ->assertSee('1 lançamento')
            ->assertSee('tableFilters%5Bperiodo%5D%5Bfrom%5D=2026-10-01&amp;tableFilters%5Bperiodo%5D%5Buntil%5D=2026-10-31', escape: false);

        Livewire::test(CashByCategoryWidget::class, ['filters' => $october])
            ->assertSee('Gastos da JUBAN GO em outubro de 2026')
            ->assertSee('Eventos e congressos')
            ->assertSee('R$ 480,00 · 100%')
            ->assertDontSee('Ofertas e Doações')
            ->assertSee('Nenhuma receita no período.');

        Livewire::test(CashByCategoryWidget::class, ['filters' => ['from' => '2026-01-01', 'until' => '2026-01-31']])
            ->assertSee('Nenhum gasto no período.')
            ->assertSee('Nenhuma receita no período.');
    }

    public function test_monthly_chart_has_one_bar_per_month_scaled_to_the_highest(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]));

        $widget = Livewire::test(CashByCategoryWidget::class, ['filters' => ['from' => '2026-09-01', 'until' => '2026-11-30']])->instance();
        $chart = $widget->getMonthlyChart($widget->getSummary());

        $this->assertSame('em reais', $chart['unit']);
        $this->assertSame(['set', 'out', 'nov'], array_column($chart['bars'], 'label'));
        $this->assertSame(['0', '480', '0'], array_column($chart['bars'], 'value'));
        $this->assertSame([0, CashByCategoryWidget::MAX_BAR_HEIGHT, 0], array_column($chart['bars'], 'height'));
    }

    public function test_other_roles_cannot_open_the_summary(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::MINISTER, 'is_active' => true]));

        $this->get('/juban')->assertForbidden();
    }
}
