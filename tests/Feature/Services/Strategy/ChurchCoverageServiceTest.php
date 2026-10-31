<?php

namespace Tests\Feature\Services\Strategy;

use App\Enums\PublishStatus;
use App\Enums\StrategyIndicator;
use App\Models\Church;
use App\Models\Municipality;
use App\Models\MunicipalityIndicator;
use App\Models\SocialProject;
use App\Services\Strategy\ChurchCoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchCoverageServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('strategy.priority_index', [
            'weights' => [
                'population' => 0.30,
                'growth' => 0.20,
                'non_evangelical_pct' => 0.25,
                'church_absence' => 0.25,
            ],
            'church_absence_decay' => 'inverse_proportional',
            'missing_data_strategy' => 'reweight_available',
        ]);
    }

    public function test_municipality_coverage_calculates_real_time_metrics_with_active_churches_only(): void
    {
        $muni = Municipality::factory()->create([
            'ibge_code' => '5208707',
            'name' => 'Goiânia',
            'immediate_region_code' => '520001',
            'immediate_region_name' => 'Goiânia',
            'intermediate_region_code' => '5201',
            'intermediate_region_name' => 'Goiânia',
        ]);

        // Active churches
        Church::factory()->count(2)->create([
            'municipality_id' => $muni->id,
            'city' => 'Goiânia',
            'is_active' => true,
        ]);

        // Inactive church (should NOT be counted)
        Church::factory()->create([
            'municipality_id' => $muni->id,
            'city' => 'Goiânia',
            'is_active' => false,
        ]);

        // Published social project
        $activeChurch = Church::where('municipality_id', $muni->id)->where('is_active', true)->first();
        SocialProject::factory()->create([
            'church_id' => $activeChurch->id,
            'status' => PublishStatus::PUBLISHED,
            'published_at' => now(),
        ]);

        // Draft social project (should NOT be counted)
        SocialProject::factory()->create([
            'church_id' => $activeChurch->id,
            'status' => PublishStatus::DRAFT,
        ]);

        // Indicators
        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muni->id,
            'indicator' => StrategyIndicator::POPULATION_ESTIMATE,
            'period' => '2024',
            'value' => 1500000.0,
        ]);

        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muni->id,
            'indicator' => StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE,
            'period' => '2024',
            'value' => 900000.0,
        ]);

        $service = app(ChurchCoverageService::class);
        $coverage = $service->getMunicipalityCoverage($muni);

        $this->assertSame(2, $coverage->activeChurchesCount);
        $this->assertSame(1, $coverage->publishedSocialProjectsCount);
        $this->assertSame(1500000, $coverage->population);
        $this->assertSame(900000, $coverage->nonEvangelicalPopulation);
        $this->assertSame(750000.0, $coverage->inhabitantsPerChurch);
        $this->assertSame(450000.0, $coverage->nonEvangelicalsPerChurch);
    }

    public function test_municipality_with_no_church_returns_null_for_per_church_ratios_and_full_absence_score(): void
    {
        $muni = Municipality::factory()->create([
            'ibge_code' => '5201108',
            'name' => 'Anápolis',
        ]);

        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muni->id,
            'indicator' => StrategyIndicator::POPULATION_ESTIMATE,
            'period' => '2024',
            'value' => 400000.0,
        ]);

        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muni->id,
            'indicator' => StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE,
            'period' => '2024',
            'value' => 240000.0,
        ]);

        $service = app(ChurchCoverageService::class);
        $coverage = $service->getMunicipalityCoverage($muni);

        $this->assertSame(0, $coverage->activeChurchesCount);
        $this->assertNull($coverage->inhabitantsPerChurch);
        $this->assertNull($coverage->nonEvangelicalsPerChurch);
        $this->assertSame(0, $coverage->publishedSocialProjectsCount);
        $this->assertSame(100.0, $coverage->priorityBreakdown['church_absence']);
    }

    public function test_contagem_de_igrejas_reflete_mudancas_no_banco_em_tempo_real_sem_cache(): void
    {
        $muni = Municipality::factory()->create(['name' => 'Rio Verde']);
        $service = app(ChurchCoverageService::class);

        $this->assertSame(0, $service->getMunicipalityCoverage($muni)->activeChurchesCount);

        // Register and activate church
        $church = Church::factory()->create([
            'municipality_id' => $muni->id,
            'city' => 'Rio Verde',
            'is_active' => true,
        ]);

        $this->assertSame(1, $service->getMunicipalityCoverage($muni)->activeChurchesCount);

        // Deactivate church
        $church->update(['is_active' => false]);
        $this->assertSame(0, $service->getMunicipalityCoverage($muni)->activeChurchesCount);
    }

    public function test_calculates_coverage_for_all_municipalities_including_priority_index_across_dataset(): void
    {
        $muniA = Municipality::factory()->create(['name' => 'Cidade Grande', 'ibge_code' => '5200010']);
        $muniB = Municipality::factory()->create(['name' => 'Cidade Pequena', 'ibge_code' => '5200020']);

        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muniA->id,
            'indicator' => StrategyIndicator::POPULATION_ESTIMATE,
            'value' => 500000.0,
        ]);
        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muniA->id,
            'indicator' => StrategyIndicator::POPULATION_GROWTH,
            'value' => 15.0,
        ]);
        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muniA->id,
            'indicator' => StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022,
            'value' => 30.0,
        ]);

        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muniB->id,
            'indicator' => StrategyIndicator::POPULATION_ESTIMATE,
            'value' => 10000.0,
        ]);
        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muniB->id,
            'indicator' => StrategyIndicator::POPULATION_GROWTH,
            'value' => 2.0,
        ]);
        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muniB->id,
            'indicator' => StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022,
            'value' => 40.0,
        ]);

        $service = app(ChurchCoverageService::class);
        $all = $service->getAllMunicipalitiesCoverage();

        $this->assertCount(2, $all);

        $covA = $all->firstWhere('municipalityId', $muniA->id);
        $covB = $all->firstWhere('municipalityId', $muniB->id);

        $this->assertGreaterThan($covB->priorityIndex, $covA->priorityIndex);
        $this->assertLessThanOrEqual(100.0, $covA->priorityIndex);
        $this->assertGreaterThanOrEqual(0.0, $covB->priorityIndex);
    }

    public function test_handles_municipality_without_ibge_indicators_gracefully_in_batch_calculation(): void
    {
        $muniWithData = Municipality::factory()->create(['name' => 'Com Dados']);
        $muniWithoutData = Municipality::factory()->create(['name' => 'Sem Dados']);

        MunicipalityIndicator::factory()->create([
            'municipality_id' => $muniWithData->id,
            'indicator' => StrategyIndicator::POPULATION_ESTIMATE,
            'value' => 50000.0,
        ]);

        $service = app(ChurchCoverageService::class);
        $all = $service->getAllMunicipalitiesCoverage();

        $covWithout = $all->firstWhere('municipalityId', $muniWithoutData->id);

        $this->assertNotNull($covWithout);
        $this->assertNull($covWithout->population);
        $this->assertNotNull($covWithout->priorityIndex);
    }

    public function test_regional_coverage_aggregates_metrics_by_immediate_and_intermediate_regions(): void
    {
        $m1 = Municipality::factory()->create([
            'immediate_region_code' => '520001',
            'immediate_region_name' => 'Região Imediata 1',
            'intermediate_region_code' => '5201',
            'intermediate_region_name' => 'Região Intermediária Central',
        ]);
        $m2 = Municipality::factory()->create([
            'immediate_region_code' => '520001',
            'immediate_region_name' => 'Região Imediata 1',
            'intermediate_region_code' => '5201',
            'intermediate_region_name' => 'Região Intermediária Central',
        ]);
        $m3 = Municipality::factory()->create([
            'immediate_region_code' => '520002',
            'immediate_region_name' => 'Região Imediata 2',
            'intermediate_region_code' => '5202',
            'intermediate_region_name' => 'Região Intermediária Sul',
        ]);

        // M1 has 2 churches
        Church::factory()->count(2)->create(['municipality_id' => $m1->id, 'is_active' => true]);
        // M2 has 0 churches
        // M3 has 1 church
        Church::factory()->create(['municipality_id' => $m3->id, 'is_active' => true]);

        MunicipalityIndicator::factory()->create(['municipality_id' => $m1->id, 'indicator' => StrategyIndicator::POPULATION_ESTIMATE, 'value' => 100000]);
        MunicipalityIndicator::factory()->create(['municipality_id' => $m1->id, 'indicator' => StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE, 'value' => 60000]);

        MunicipalityIndicator::factory()->create(['municipality_id' => $m2->id, 'indicator' => StrategyIndicator::POPULATION_ESTIMATE, 'value' => 40000]);
        MunicipalityIndicator::factory()->create(['municipality_id' => $m2->id, 'indicator' => StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE, 'value' => 25000]);

        MunicipalityIndicator::factory()->create(['municipality_id' => $m3->id, 'indicator' => StrategyIndicator::POPULATION_ESTIMATE, 'value' => 50000]);
        MunicipalityIndicator::factory()->create(['municipality_id' => $m3->id, 'indicator' => StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE, 'value' => 30000]);

        $service = app(ChurchCoverageService::class);
        $immediate = $service->getImmediateRegionsCoverage();

        $this->assertTrue($immediate->has('520001'));
        $this->assertTrue($immediate->has('520002'));

        $reg1 = $immediate->get('520001');
        $this->assertSame(2, $reg1->totalMunicipalitiesCount);
        $this->assertSame(1, $reg1->municipalitiesWithChurchCount);
        $this->assertSame(1, $reg1->municipalitiesWithoutChurchCount);
        $this->assertSame(50.0, $reg1->coveragePercentage);
        $this->assertSame(2, $reg1->activeChurchesCount);
        $this->assertSame(140000, $reg1->totalPopulation);
        $this->assertSame(85000, $reg1->totalNonEvangelicalPopulation);
        $this->assertSame(70000.0, $reg1->inhabitantsPerChurch);
        $this->assertSame(42500.0, $reg1->nonEvangelicalsPerChurch);

        $state = $service->getStateCoverage();
        $this->assertSame(3, $state->totalMunicipalitiesCount);
        $this->assertSame(2, $state->municipalitiesWithChurchCount);
        $this->assertSame(1, $state->municipalitiesWithoutChurchCount);
        $this->assertSame(3, $state->activeChurchesCount);
        $this->assertSame(190000, $state->totalPopulation);
    }
}
