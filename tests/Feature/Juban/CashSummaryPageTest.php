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
    public function test_panel_home_shows_current_month_summary(UserRole $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role, 'is_active' => true]));

        $this->assertSame(url('/juban'), Dashboard::getUrl());

        $this->get('/juban')
            ->assertSuccessful()
            ->assertSee('Resumo de caixa')
            ->assertSee('R$ 1.250,50')
            ->assertSee('01/11/2026 a 30/11/2026')
            ->assertSee('Ofertas e Doações')
            ->assertDontSee('Eventos e congressos');
    }

    public function test_widgets_follow_the_selected_period(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]));

        $october = ['from' => '2026-10-01', 'until' => '2026-10-31'];

        Livewire::test(CashSummaryStatsWidget::class, ['filters' => $october])
            ->assertSee('R$ 480,00')
            ->assertSee('- R$ 480,00')
            ->assertSee('1 lançamento')
            ->assertSee('tableFilters%5Bperiodo%5D%5Bfrom%5D=2026-10-01&amp;tableFilters%5Bperiodo%5D%5Buntil%5D=2026-10-31', escape: false);

        Livewire::test(CashByCategoryWidget::class, ['filters' => $october])
            ->assertSee('Eventos e congressos')
            ->assertSee('100,0%')
            ->assertDontSee('Ofertas e Doações');

        Livewire::test(CashByCategoryWidget::class, ['filters' => ['from' => '2026-01-01', 'until' => '2026-01-31']])
            ->assertSee('Nenhum lançamento no período.');
    }

    public function test_other_roles_cannot_open_the_summary(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::MINISTER, 'is_active' => true]));

        $this->get('/juban')->assertForbidden();
    }
}
