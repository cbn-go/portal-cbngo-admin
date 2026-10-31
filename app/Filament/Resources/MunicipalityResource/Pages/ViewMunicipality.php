<?php

namespace App\Filament\Resources\MunicipalityResource\Pages;

use App\DTOs\Strategy\MunicipalityCoverageData;
use App\Enums\PriorityBand;
use App\Enums\StrategyIndicator;
use App\Filament\Concerns\HasStrategySourceFooter;
use App\Filament\Resources\ChurchResource;
use App\Filament\Resources\MunicipalityResource;
use App\Filament\Resources\SocialProjectResource;
use App\Models\Church;
use App\Models\Municipality;
use App\Models\MunicipalityIndicator;
use App\Models\SocialProject;
use App\Services\Strategy\ChurchCoverageService;
use App\Services\Strategy\StateIndicatorService;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property Municipality $record
 */
class ViewMunicipality extends ViewRecord
{
    use HasStrategySourceFooter;

    protected static string $resource = MunicipalityResource::class;

    protected static string $view = 'filament.resources.municipality-resource.pages.view-municipality';

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    public function getSubheading(): string
    {
        return "Região imediata de {$this->record->immediate_region_name} · Região intermediária de {$this->record->intermediate_region_name} · Código IBGE {$this->record->ibge_code}";
    }

    public function coverage(): MunicipalityCoverageData
    {
        return once(fn (): MunicipalityCoverageData => app(ChurchCoverageService::class)->getMunicipalityCoverage($this->record));
    }

    public function priorityBand(): ?PriorityBand
    {
        return PriorityBand::forScore($this->coverage()->priorityIndex);
    }

    /**
     * Fatores do índice com rótulo, peso e nota (0 a 100).
     *
     * @return list<array{label: string, weight: float, score: ?float}>
     */
    public function priorityFactors(): array
    {
        /** @var array<string, float> $weights */
        $weights = config('strategy.priority_index.weights', []);
        /** @var array<string, array{label: string}> $metadata */
        $metadata = config('strategy.priority_index.metadata', []);

        // A chave do detalhamento difere da configuração só para não evangélicos.
        $breakdownKeys = ['non_evangelical_pct' => 'non_evangelical'];

        return collect($weights)->map(fn (float $weight, string $key): array => [
            'label' => $metadata[$key]['label'] ?? $key,
            'weight' => $weight,
            'score' => $this->coverage()->priorityBreakdown[$breakdownKeys[$key] ?? $key] ?? null,
        ])->values()->all();
    }

    /**
     * Indicadores agrupados por seção, com o valor de Goiás para comparação.
     *
     * @return array<string, list<array{indicator: StrategyIndicator, row: ?MunicipalityIndicator, state: ?float, comparison: ?string}>>
     */
    public function indicatorGroups(): array
    {
        $service = app(StateIndicatorService::class);
        $groups = [];

        foreach (StrategyIndicator::cases() as $indicator) {
            $row = $service->valueFor($this->record, $indicator);
            $state = $service->stateValue($indicator);

            $groups[$indicator->group()][] = [
                'indicator' => $indicator,
                'row' => $row,
                'state' => $state,
                'comparison' => $row !== null ? $this->compare($indicator, (float) $row->value, $state) : null,
            ];
        }

        return $groups;
    }

    /**
     * @return Collection<int, Church>
     */
    public function churches(): Collection
    {
        return $this->record->churches()->orderByDesc('is_active')->orderBy('name')->get();
    }

    /**
     * @return Collection<int, SocialProject>
     */
    public function socialProjects(): Collection
    {
        return SocialProject::query()
            ->with('church:id,name')
            ->whereHas('church', fn ($query) => $query->where('municipality_id', $this->record->id))
            ->latest()
            ->get();
    }

    public function churchUrl(Church $church): string
    {
        return ChurchResource::getUrl('edit', ['record' => $church]);
    }

    public function socialProjectUrl(SocialProject $project): string
    {
        return SocialProjectResource::getUrl('edit', ['record' => $project]);
    }

    /**
     * Percentuais: diferença em pontos percentuais. Médias (PIB per capita,
     * densidade): diferença relativa. Contagens: participação no total do estado.
     */
    private function compare(StrategyIndicator $indicator, float $value, ?float $state): ?string
    {
        if ($state === null || $state == 0.0) {
            return null;
        }

        return match ($indicator->unit()) {
            '%', 'p.p.' => $this->signed($value - $state, ' p.p.'),
            'R$', 'hab/km²' => $this->signed(($value / $state - 1) * 100, '%'),
            default => number_format($value / $state * 100, 2, ',', '.').'% de Goiás',
        };
    }

    private function signed(float $value, string $suffix): string
    {
        return ($value > 0 ? '+' : '').number_format($value, 2, ',', '.').$suffix;
    }
}
