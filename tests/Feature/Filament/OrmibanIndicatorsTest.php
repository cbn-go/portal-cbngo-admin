<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Ormiban\Widgets\AgeChartWidget;
use App\Filament\Ormiban\Widgets\MinisterSummaryWidget;
use App\Filament\Ormiban\Widgets\MinistryAgeChartWidget;
use App\Filament\Ormiban\Widgets\OrdinationsByYearChartWidget;
use App\Filament\Pages\Ormiban\Indicators;
use App\Models\Minister;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Mechanisms\ComponentRegistry;
use Tests\TestCase;

class OrmibanIndicatorsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $conventionEditor;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->conventionEditor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_and_editor_can_open_the_indicators_page(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/ormiban/indicadores')
            ->assertSuccessful()
            ->assertSee('Ormiban / Indicadores')
            ->assertSee((string) config('app.timezone'));

        $this->actingAs($this->conventionEditor)
            ->get('/admin/ormiban/indicadores')
            ->assertSuccessful();
    }

    public function test_roles_without_admin_access_receive_forbidden(): void
    {
        $minister = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
        ]);
        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);
        $representative = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);
        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
        ]);

        foreach ([$minister, $author, $representative, $inactiveAdmin] as $user) {
            $this->actingAs($user)
                ->get('/admin/ormiban/indicadores')
                ->assertForbidden();
        }
    }

    public function test_function_filter_changes_the_summary_counts(): void
    {
        $pastor = Minister::factory()->create([
            'function' => 'Pastor',
            'status' => Minister::STATUS_ACTIVE,
            'birth_date' => '1980-01-15',
        ]);
        $pastor->historyEvents()->create([
            'occurred_on' => '2001-05-20',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);

        Minister::factory()->create([
            'function' => 'Missionário',
            'status' => Minister::STATUS_ACTIVE,
            'birth_date' => '1982-03-10',
        ]);

        $this->actingAs($this->conventionEditor);

        Livewire::test(Indicators::class)
            ->assertSee('Idade de ministério')
            ->assertSee('Ordenações por ano')
            ->assertDontSee('Idade de ordenação')
            ->assertDontSee('Com diplomação')
            ->assertSee('2 com status ativo no filtro')
            ->assertSee('50,0%')
            ->fillForm(['function' => 'Pastor'], 'filtersForm')
            ->assertSet('filters.function', 'Pastor');

        Livewire::test(MinisterSummaryWidget::class, [
            'filters' => ['function' => 'Pastor'],
        ])
            ->assertSee('1 com status ativo no filtro')
            ->assertSee('100,0%')
            ->assertDontSee('50,0%');
    }

    public function test_indicator_widgets_are_registered_and_stay_off_the_dashboard(): void
    {
        $registry = app(ComponentRegistry::class);
        $widgets = [
            MinisterSummaryWidget::class,
            AgeChartWidget::class,
            MinistryAgeChartWidget::class,
            OrdinationsByYearChartWidget::class,
        ];

        foreach ($widgets as $widget) {
            $this->assertSame($widget, $registry->getClass($registry->getName($widget)));
            $this->assertNotContains($widget, Filament::getPanel('admin')->getWidgets());
        }
    }
}
