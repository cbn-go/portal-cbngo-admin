<?php

namespace Tests\Feature\Filament\Strategy;

use App\Enums\StrategyIndicator;
use App\Enums\UserRole;
use App\Filament\Pages\Strategy\GoiasOverview;
use App\Filament\Pages\Strategy\Methodology;
use App\Filament\Resources\ChurchResource;
use App\Filament\Resources\MunicipalityResource;
use App\Filament\Resources\MunicipalityResource\Pages\ViewMunicipality;
use App\Filament\Strategy\Widgets\StrategyKpisWidget;
use App\Models\Church;
use App\Models\SocialProject;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StrategyPagesTest extends TestCase
{
    use CreatesStrategyData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]));
    }

    public function test_overview_kpis_use_state_values_and_church_coverage(): void
    {
        $withChurch = $this->municipality(['immediate_region_code' => '520001', 'immediate_region_name' => 'Goiânia'], [
            'population_estimate' => 600000,
            'religion_evangelical_2022' => 150000,
            'population_10_plus_2022' => 500000,
        ]);
        $this->municipality(['immediate_region_code' => '520002', 'immediate_region_name' => 'Anápolis'], [
            'population_estimate' => 400000,
            'religion_evangelical_2022' => 200000,
            'population_10_plus_2022' => 500000,
        ]);
        Church::factory()->count(2)->create(['municipality_id' => $withChurch->id, 'is_active' => true]);

        Livewire::test(StrategyKpisWidget::class)
            ->assertSee('1.000.000')
            ->assertSee('Estimativa IBGE 2025')
            ->assertSee('35,00%')
            ->assertSee('1 de 2')
            ->assertSee('500.000');

        Livewire::test(GoiasOverview::class)
            ->assertSee('Cobertura por região imediata')
            ->assertSeeInOrder(['Anápolis', 'Goiânia'])
            ->assertSee('Última sincronização');
    }

    public function test_overview_warns_when_indicators_were_never_synced(): void
    {
        $this->municipality();

        Livewire::test(StrategyKpisWidget::class)
            ->assertSee('Indicador ainda não sincronizado')
            ->assertDontSee('0,00%');

        Livewire::test(GoiasOverview::class)
            ->assertSee('Indicadores ainda não sincronizados');
    }

    public function test_detail_page_shows_indicators_with_year_source_and_state_comparison(): void
    {
        $municipality = $this->municipality(['name' => 'Rio Verde'], [
            'population_estimate' => 250000,
            'religion_evangelical_2022' => 60000,
            'population_10_plus_2022' => 200000,
            'religion_evangelical_pct_2022' => 30,
        ]);
        $this->municipality([], [
            'population_estimate' => 750000,
            'religion_evangelical_2022' => 100000,
            'population_10_plus_2022' => 200000,
        ]);
        $church = Church::factory()->create(['municipality_id' => $municipality->id, 'name' => 'Igreja Batista Central de Rio Verde', 'is_active' => true]);
        SocialProject::factory()->published()->create(['church_id' => $church->id, 'title' => 'Reforço Escolar']);

        Livewire::test(ViewMunicipality::class, ['record' => $municipality->getRouteKey()])
            ->assertSee('Rio Verde')
            ->assertSee(StrategyIndicator::POPULATION_ESTIMATE->label())
            ->assertSee('250.000')
            ->assertSee('2025')
            ->assertSee(StrategyIndicator::POPULATION_ESTIMATE->source())
            // Goiás tem 1.000.000; Rio Verde responde por 25%.
            ->assertSee('1.000.000')
            ->assertSee('25,00% de Goiás')
            // 30% contra 40% no estado.
            ->assertSee('-10,00 p.p.')
            ->assertSee('Sem dados do IBGE')
            ->assertSee('Igreja Batista Central de Rio Verde')
            ->assertSee(ChurchResource::getUrl('edit', ['record' => $church]))
            ->assertSee('Reforço Escolar');
    }

    public function test_methodology_lists_every_indicator_with_its_sync_state(): void
    {
        $this->municipality([], ['population_census_2022' => 1000]);

        $component = Livewire::test(Methodology::class);

        foreach (StrategyIndicator::cases() as $indicator) {
            $component->assertSee($indicator->label());
        }

        $component
            ->assertSee('SIDRA 4714')
            ->assertSee(now()->format('d/m/Y'))
            ->assertSee('Nunca sincronizado')
            ->assertSee('O número de batistas por município vem do Censo 2010.');
    }

    public function test_region_links_open_the_municipality_list_filtered(): void
    {
        $this->municipality(['immediate_region_code' => '520010', 'immediate_region_name' => 'Ceres']);

        Livewire::test(GoiasOverview::class)
            ->assertSee(e(MunicipalityResource::getUrl('index', ['tableFilters' => ['immediate_region_code' => ['value' => '520010']]])), false);
    }
}
