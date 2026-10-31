<?php

namespace App\Services\Strategy;

use App\DTOs\Strategy\MunicipalityCoverageData;
use App\DTOs\Strategy\RegionalCoverageData;
use App\Enums\StrategyIndicator;
use App\Models\Church;
use App\Models\Municipality;
use App\Models\MunicipalityIndicator;
use App\Models\SocialProject;
use Illuminate\Support\Collection;

class ChurchCoverageService
{
    public function __construct(
        protected PriorityIndexCalculator $calculator,
    ) {}

    /**
     * Obtém as métricas de cobertura de um município individual em tempo real.
     */
    public function getMunicipalityCoverage(Municipality|int $municipality): MunicipalityCoverageData
    {
        $muni = $municipality instanceof Municipality
            ? $municipality
            : Municipality::query()->findOrFail($municipality);

        // Contagem em tempo real de igrejas ativas (sem cache)
        $activeChurchesCount = Church::query()
            ->where('municipality_id', $muni->id)
            ->where('is_active', true)
            ->count();

        // Contagem em tempo real de projetos sociais publicados
        $publishedProjectsCount = SocialProject::published()
            ->whereHas('church', function ($query) use ($muni): void {
                $query->where('municipality_id', $muni->id);
            })
            ->count();

        // Indicadores do município
        /** @var Collection<int, MunicipalityIndicator> $indicators */
        $indicators = MunicipalityIndicator::query()
            ->where('municipality_id', $muni->id)
            ->whereIn('indicator', [
                StrategyIndicator::POPULATION_ESTIMATE,
                StrategyIndicator::POPULATION_CENSUS_2022,
                StrategyIndicator::POPULATION_GROWTH,
                StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE,
                StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022,
            ])
            ->get();

        $popRow = $indicators->firstWhere('indicator', StrategyIndicator::POPULATION_ESTIMATE)
            ?? $indicators->firstWhere('indicator', StrategyIndicator::POPULATION_CENSUS_2022);
        $population = $popRow !== null ? (int) round((float) $popRow->value) : null;

        $growthRow = $indicators->firstWhere('indicator', StrategyIndicator::POPULATION_GROWTH);
        $growth = $growthRow !== null ? (float) $growthRow->value : null;

        $nonEvRow = $indicators->firstWhere('indicator', StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE);
        $nonEvangelicalPopulation = $nonEvRow !== null ? (int) round((float) $nonEvRow->value) : null;

        $evPctRow = $indicators->firstWhere('indicator', StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022);
        $nonEvangelicalPct = null;
        if ($evPctRow !== null) {
            $nonEvangelicalPct = max(0.0, min(100.0, 100.0 - (float) $evPctRow->value));
        }

        // Relações por igreja
        $inhabitantsPerChurch = null;
        $nonEvangelicalsPerChurch = null;

        if ($activeChurchesCount > 0) {
            if ($population !== null) {
                $inhabitantsPerChurch = round($population / $activeChurchesCount, 2);
            }
            if ($nonEvangelicalPopulation !== null) {
                $nonEvangelicalsPerChurch = round($nonEvangelicalPopulation / $activeChurchesCount, 2);
            }
        }

        $bounds = $this->getIndicatorBounds();
        $priorityCalc = $this->calculator->calculate(
            population: $population !== null ? (float) $population : null,
            growth: $growth,
            nonEvangelicalPct: $nonEvangelicalPct,
            activeChurches: $activeChurchesCount,
            bounds: $bounds,
        );

        return new MunicipalityCoverageData(
            municipalityId: $muni->id,
            ibgeCode: $muni->ibge_code,
            name: $muni->name,
            immediateRegionCode: $muni->immediate_region_code,
            immediateRegionName: $muni->immediate_region_name,
            intermediateRegionCode: $muni->intermediate_region_code,
            intermediateRegionName: $muni->intermediate_region_name,
            activeChurchesCount: $activeChurchesCount,
            population: $population,
            inhabitantsPerChurch: $inhabitantsPerChurch,
            nonEvangelicalPopulation: $nonEvangelicalPopulation,
            nonEvangelicalsPerChurch: $nonEvangelicalsPerChurch,
            publishedSocialProjectsCount: $publishedProjectsCount,
            priorityIndex: $priorityCalc['score'],
            priorityBreakdown: $priorityCalc['breakdown'],
        );
    }

    /**
     * Obtém as métricas de cobertura de todos os municípios, calculadas em lote com alta performance.
     *
     * @return Collection<int, MunicipalityCoverageData>
     */
    public function getAllMunicipalitiesCoverage(): Collection
    {
        /** @var Collection<int, Municipality> $municipalities */
        $municipalities = Municipality::query()
            ->orderBy('name')
            ->get();

        if ($municipalities->isEmpty()) {
            return collect();
        }

        // 1. Contagens de igrejas ativas agrupadas por municipality_id
        /** @var array<int, int> $churchesCountMap */
        $churchesCountMap = Church::query()
            ->where('is_active', true)
            ->whereNotNull('municipality_id')
            ->selectRaw('municipality_id, count(*) as total')
            ->groupBy('municipality_id')
            ->pluck('total', 'municipality_id')
            ->map(fn ($val): int => (int) $val)
            ->all();

        // 2. Contagens de projetos sociais publicados agrupados por municipality_id
        /** @var array<int, int> $projectsCountMap */
        $projectsCountMap = SocialProject::published()
            ->join('churches', 'social_projects.church_id', '=', 'churches.id')
            ->whereNotNull('churches.municipality_id')
            ->selectRaw('churches.municipality_id, count(social_projects.id) as total')
            ->groupBy('churches.municipality_id')
            ->pluck('total', 'churches.municipality_id')
            ->map(fn ($val): int => (int) $val)
            ->all();

        // 3. Indicadores agrupados por municipality_id
        /** @var Collection<int, Collection<int, MunicipalityIndicator>> $indicatorsGrouped */
        $indicatorsGrouped = MunicipalityIndicator::query()
            ->whereIn('indicator', [
                StrategyIndicator::POPULATION_ESTIMATE,
                StrategyIndicator::POPULATION_CENSUS_2022,
                StrategyIndicator::POPULATION_GROWTH,
                StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE,
                StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022,
            ])
            ->get()
            ->groupBy('municipality_id');

        // 4. Prepara dados intermediários para calcular os limites globais (bounds)
        $muniDataList = [];
        $populations = [];
        $growths = [];

        foreach ($municipalities as $muni) {
            /** @var Collection<int, MunicipalityIndicator> $muniIndicators */
            $muniIndicators = $indicatorsGrouped->get($muni->id, collect());

            $popRow = $muniIndicators->firstWhere('indicator', StrategyIndicator::POPULATION_ESTIMATE)
                ?? $muniIndicators->firstWhere('indicator', StrategyIndicator::POPULATION_CENSUS_2022);
            $pop = $popRow !== null ? (int) round((float) $popRow->value) : null;
            if ($pop !== null) {
                $populations[] = (float) $pop;
            }

            $growthRow = $muniIndicators->firstWhere('indicator', StrategyIndicator::POPULATION_GROWTH);
            $growth = $growthRow !== null ? (float) $growthRow->value : null;
            if ($growth !== null) {
                $growths[] = $growth;
            }

            $nonEvRow = $muniIndicators->firstWhere('indicator', StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE);
            $nonEvPop = $nonEvRow !== null ? (int) round((float) $nonEvRow->value) : null;

            $evPctRow = $muniIndicators->firstWhere('indicator', StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022);
            $nonEvPct = null;
            if ($evPctRow !== null) {
                $nonEvPct = max(0.0, min(100.0, 100.0 - (float) $evPctRow->value));
            }

            $activeChurches = $churchesCountMap[$muni->id] ?? 0;
            $projectsCount = $projectsCountMap[$muni->id] ?? 0;

            $muniDataList[] = [
                'muni' => $muni,
                'population' => $pop,
                'growth' => $growth,
                'nonEvPop' => $nonEvPop,
                'nonEvPct' => $nonEvPct,
                'activeChurches' => $activeChurches,
                'projectsCount' => $projectsCount,
            ];
        }

        $bounds = [
            'min_pop' => ! empty($populations) ? min($populations) : 0.0,
            'max_pop' => ! empty($populations) ? max($populations) : 100000.0,
            'min_growth' => ! empty($growths) ? min($growths) : 0.0,
            'max_growth' => ! empty($growths) ? max($growths) : 20.0,
        ];

        // 5. Monta os DTOs com o cálculo do índice de prioridade
        $coverageCollection = collect();

        foreach ($muniDataList as $item) {
            $muni = $item['muni'];
            $activeChurches = $item['activeChurches'];
            $pop = $item['population'];
            $nonEvPop = $item['nonEvPop'];

            $inhabitantsPerChurch = null;
            $nonEvangelicalsPerChurch = null;

            if ($activeChurches > 0) {
                if ($pop !== null) {
                    $inhabitantsPerChurch = round($pop / $activeChurches, 2);
                }
                if ($nonEvPop !== null) {
                    $nonEvangelicalsPerChurch = round($nonEvPop / $activeChurches, 2);
                }
            }

            $priorityCalc = $this->calculator->calculate(
                population: $pop !== null ? (float) $pop : null,
                growth: $item['growth'],
                nonEvangelicalPct: $item['nonEvPct'],
                activeChurches: $activeChurches,
                bounds: $bounds,
            );

            $coverageCollection->push(new MunicipalityCoverageData(
                municipalityId: $muni->id,
                ibgeCode: $muni->ibge_code,
                name: $muni->name,
                immediateRegionCode: $muni->immediate_region_code,
                immediateRegionName: $muni->immediate_region_name,
                intermediateRegionCode: $muni->intermediate_region_code,
                intermediateRegionName: $muni->intermediate_region_name,
                activeChurchesCount: $activeChurches,
                population: $pop,
                inhabitantsPerChurch: $inhabitantsPerChurch,
                nonEvangelicalPopulation: $nonEvPop,
                nonEvangelicalsPerChurch: $nonEvangelicalsPerChurch,
                publishedSocialProjectsCount: $item['projectsCount'],
                priorityIndex: $priorityCalc['score'],
                priorityBreakdown: $priorityCalc['breakdown'],
            ));
        }

        // Ordena por prioridade determinística
        return $coverageCollection->sort(fn ($a, $b) => $this->calculator->comparePriorities($a, $b))->values();
    }

    /**
     * Agrega as métricas de cobertura por Região Imediata.
     *
     * @return Collection<string, RegionalCoverageData>
     */
    public function getImmediateRegionsCoverage(): Collection
    {
        $all = $this->getAllMunicipalitiesCoverage();

        return $all->groupBy('immediateRegionCode')->map(function (Collection $group, string $regionCode): RegionalCoverageData {
            $regionName = $group->first()->immediateRegionName;

            return $this->aggregateRegionalGroup('immediate', $regionCode, $regionName, $group);
        });
    }

    /**
     * Agrega as métricas de cobertura por Região Intermediária.
     *
     * @return Collection<string, RegionalCoverageData>
     */
    public function getIntermediateRegionsCoverage(): Collection
    {
        $all = $this->getAllMunicipalitiesCoverage();

        return $all->groupBy('intermediateRegionCode')->map(function (Collection $group, string $regionCode): RegionalCoverageData {
            $regionName = $group->first()->intermediateRegionName;

            return $this->aggregateRegionalGroup('intermediate', $regionCode, $regionName, $group);
        });
    }

    /**
     * Agrega o sumário de cobertura de todo o estado de Goiás.
     */
    public function getStateCoverage(): RegionalCoverageData
    {
        $all = $this->getAllMunicipalitiesCoverage();

        return $this->aggregateRegionalGroup('state', '52', 'Goiás', $all);
    }

    /**
     * @param  Collection<int, MunicipalityCoverageData>  $group
     */
    protected function aggregateRegionalGroup(
        string $regionType,
        string $regionCode,
        string $regionName,
        Collection $group,
    ): RegionalCoverageData {
        $totalMunicipalities = $group->count();
        $withChurch = $group->filter(fn (MunicipalityCoverageData $c) => $c->activeChurchesCount > 0)->count();
        $withoutChurch = $totalMunicipalities - $withChurch;
        $coveragePercentage = $totalMunicipalities > 0
            ? round(($withChurch / $totalMunicipalities) * 100.0, 2)
            : 0.0;

        $totalActiveChurches = $group->sum('activeChurchesCount');
        $totalPopulation = (int) $group->sum('population');
        $totalNonEvangelical = (int) $group->sum('nonEvangelicalPopulation');
        $totalProjects = (int) $group->sum('publishedSocialProjectsCount');

        $inhabitantsPerChurch = null;
        $nonEvangelicalsPerChurch = null;

        if ($totalActiveChurches > 0) {
            $inhabitantsPerChurch = round($totalPopulation / $totalActiveChurches, 2);
            $nonEvangelicalsPerChurch = round($totalNonEvangelical / $totalActiveChurches, 2);
        }

        return new RegionalCoverageData(
            regionType: $regionType,
            regionCode: $regionCode,
            regionName: $regionName,
            totalMunicipalitiesCount: $totalMunicipalities,
            municipalitiesWithChurchCount: $withChurch,
            municipalitiesWithoutChurchCount: $withoutChurch,
            coveragePercentage: $coveragePercentage,
            activeChurchesCount: $totalActiveChurches,
            totalPopulation: $totalPopulation,
            totalNonEvangelicalPopulation: $totalNonEvangelical,
            inhabitantsPerChurch: $inhabitantsPerChurch,
            nonEvangelicalsPerChurch: $nonEvangelicalsPerChurch,
            publishedSocialProjectsCount: $totalProjects,
        );
    }

    /**
     * Busca os limites min e max de população e crescimento para a normalização.
     *
     * @return array{min_pop: float, max_pop: float, min_growth: float, max_growth: float}
     */
    protected function getIndicatorBounds(): array
    {
        $popMin = MunicipalityIndicator::query()
            ->where('indicator', StrategyIndicator::POPULATION_ESTIMATE)
            ->min('value');
        $popMax = MunicipalityIndicator::query()
            ->where('indicator', StrategyIndicator::POPULATION_ESTIMATE)
            ->max('value');

        $growthMin = MunicipalityIndicator::query()
            ->where('indicator', StrategyIndicator::POPULATION_GROWTH)
            ->min('value');
        $growthMax = MunicipalityIndicator::query()
            ->where('indicator', StrategyIndicator::POPULATION_GROWTH)
            ->max('value');

        return [
            'min_pop' => $popMin !== null ? (float) $popMin : 0.0,
            'max_pop' => $popMax !== null ? (float) $popMax : 1000000.0,
            'min_growth' => $growthMin !== null ? (float) $growthMin : 0.0,
            'max_growth' => $growthMax !== null ? (float) $growthMax : 20.0,
        ];
    }
}
