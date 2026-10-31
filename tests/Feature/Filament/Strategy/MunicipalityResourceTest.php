<?php

namespace Tests\Feature\Filament\Strategy;

use App\Enums\UserRole;
use App\Filament\Resources\MunicipalityResource\Pages\ListMunicipalities;
use App\Models\Church;
use App\Models\Municipality;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MunicipalityResourceTest extends TestCase
{
    use CreatesStrategyData;
    use RefreshDatabase;

    private Municipality $capital;

    private Municipality $bigWithoutChurch;

    private Municipality $smallWithoutChurch;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('strategy.priority_index.weights', [
            'population' => 0.30,
            'growth' => 0.20,
            'non_evangelical_pct' => 0.25,
            'church_absence' => 0.25,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]));

        $this->capital = $this->municipality(
            ['name' => 'Goiânia', 'immediate_region_code' => '520001', 'immediate_region_name' => 'Goiânia'],
            ['population_estimate' => 1500000, 'population_growth' => 5, 'religion_evangelical_pct_2022' => 34],
        );
        $this->bigWithoutChurch = $this->municipality(
            ['name' => 'Senador Canedo', 'immediate_region_code' => '520001', 'immediate_region_name' => 'Goiânia'],
            ['population_estimate' => 180000, 'population_growth' => 15, 'religion_evangelical_pct_2022' => 38],
        );
        $this->smallWithoutChurch = $this->municipality(
            ['name' => 'Anhanguera', 'immediate_region_code' => '520020', 'immediate_region_name' => 'Catalão'],
            ['population_estimate' => 1100, 'population_growth' => 1, 'religion_evangelical_pct_2022' => 90],
        );

        Church::factory()->count(3)->create(['municipality_id' => $this->capital->id, 'is_active' => true]);
    }

    public function test_lists_every_municipality_with_coverage_columns(): void
    {
        Livewire::test(ListMunicipalities::class)
            ->assertCanSeeTableRecords([$this->capital, $this->bigWithoutChurch, $this->smallWithoutChurch])
            ->assertSee('1.500.000')
            ->assertSee('500.000');
    }

    public function test_search_filters_by_name(): void
    {
        Livewire::test(ListMunicipalities::class)
            ->searchTable('Senador')
            ->assertCanSeeTableRecords([$this->bigWithoutChurch])
            ->assertCanNotSeeTableRecords([$this->capital, $this->smallWithoutChurch]);
    }

    public function test_filters_by_region_church_population_and_priority(): void
    {
        Livewire::test(ListMunicipalities::class)
            ->filterTable('immediate_region_code', '520020')
            ->assertCanSeeTableRecords([$this->smallWithoutChurch])
            ->assertCanNotSeeTableRecords([$this->capital, $this->bigWithoutChurch]);

        Livewire::test(ListMunicipalities::class)
            ->filterTable('has_church', false)
            ->filterTable('population_band', ['20k_50k', '50k_100k', 'acima_100k'])
            ->assertCanSeeTableRecords([$this->bigWithoutChurch])
            ->assertCanNotSeeTableRecords([$this->capital, $this->smallWithoutChurch]);

        Livewire::test(ListMunicipalities::class)
            ->filterTable('has_church', true)
            ->assertCanSeeTableRecords([$this->capital])
            ->assertCanNotSeeTableRecords([$this->bigWithoutChurch, $this->smallWithoutChurch]);

        // Senador Canedo: sem igreja, populoso e crescendo rápido. Anhanguera: pequena e parada.
        Livewire::test(ListMunicipalities::class)
            ->filterTable('priority_band', 'baixa')
            ->assertCanSeeTableRecords([$this->smallWithoutChurch])
            ->assertCanNotSeeTableRecords([$this->bigWithoutChurch]);
    }

    public function test_sorts_by_priority_index_by_default_and_by_population(): void
    {
        Livewire::test(ListMunicipalities::class)
            ->assertCanSeeTableRecords([$this->bigWithoutChurch, $this->capital, $this->smallWithoutChurch], inOrder: true);

        Livewire::test(ListMunicipalities::class)
            ->sortTable('population', 'asc')
            ->assertCanSeeTableRecords([$this->smallWithoutChurch, $this->bigWithoutChurch, $this->capital], inOrder: true);
    }

    public function test_csv_export_follows_the_current_filters(): void
    {
        $component = Livewire::test(ListMunicipalities::class)
            ->filterTable('has_church', false);

        $response = $component->instance()->exportCsv();

        ob_start();
        $response->sendContent();
        $csv = (string) ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Município;"Código IBGE";"Região imediata"', $csv);
        $names = collect(explode('
', trim($csv)))->skip(1)
            ->map(fn (string $line): string => str_getcsv($line, ';')[0])
            ->all();

        $this->assertEqualsCanonicalizing(['Senador Canedo', 'Anhanguera'], $names);
        $this->assertStringContainsString('attachment; filename=municipios-goias-', (string) $response->headers->get('Content-Disposition'));
    }

    public function test_export_action_triggers_a_download(): void
    {
        Livewire::test(ListMunicipalities::class)
            ->callAction('exportCsv')
            ->assertFileDownloaded('municipios-goias-'.now()->format('Y-m-d').'.csv');
    }
}
