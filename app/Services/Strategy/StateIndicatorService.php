<?php

namespace App\Services\Strategy;

use App\Enums\StrategyIndicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Leitura dos indicadores sincronizados para as telas do Painel de Estratégia.
 *
 * O banco guarda só valores por município; o valor de Goiás é recomposto a
 * partir deles (somas e razões entre somas, nunca média de percentuais).
 * Indicador sem dados retorna null, para a tela avisar em vez de mostrar zero.
 * Registrado como scoped: as linhas são lidas uma vez por requisição.
 */
class StateIndicatorService
{
    /**
     * Última linha (maior período) de cada indicador, por município.
     *
     * @var array<string, array<int, MunicipalityIndicator>>|null
     */
    private ?array $latest = null;

    /**
     * @var array<string, ?float>
     */
    private array $stateValues = [];

    /**
     * @return array<string, array<int, MunicipalityIndicator>>
     */
    public function latestRows(): array
    {
        if ($this->latest !== null) {
            return $this->latest;
        }

        $this->latest = [];

        MunicipalityIndicator::query()
            ->orderBy('period')
            ->get(['id', 'municipality_id', 'indicator', 'period', 'value', 'source', 'fetched_at'])
            ->each(function (MunicipalityIndicator $row): void {
                // Ordenado por período: o mais recente sobrescreve os anteriores.
                $this->latest[$row->indicator->value][$row->municipality_id] = $row;
            });

        return $this->latest;
    }

    public function valueFor(Municipality|int $municipality, StrategyIndicator $indicator): ?MunicipalityIndicator
    {
        $id = $municipality instanceof Municipality ? $municipality->id : $municipality;

        return $this->latestRows()[$indicator->value][$id] ?? null;
    }

    public function stateValue(StrategyIndicator $indicator): ?float
    {
        if (array_key_exists($indicator->value, $this->stateValues)) {
            return $this->stateValues[$indicator->value];
        }

        return $this->stateValues[$indicator->value] = $this->computeStateValue($indicator);
    }

    public function lastSyncedAt(?StrategyIndicator $indicator = null): ?Carbon
    {
        $rows = $indicator !== null
            ? collect($this->latestRows()[$indicator->value] ?? [])
            : collect($this->latestRows())->flatten(1);

        /** @var Carbon|null $max */
        $max = $rows->max(fn (MunicipalityIndicator $row): Carbon => $row->fetched_at);

        return $max;
    }

    /**
     * Períodos guardados no banco para o indicador, do mais recente ao mais antigo.
     *
     * @return list<string>
     */
    public function periodsFor(StrategyIndicator $indicator): array
    {
        return MunicipalityIndicator::query()
            ->where('indicator', $indicator)
            ->distinct()
            ->orderByDesc('period')
            ->pluck('period')
            ->map(fn ($period): string => (string) $period)
            ->values()
            ->all();
    }

    public function hasAnyData(): bool
    {
        return $this->latestRows() !== [];
    }

    private function computeStateValue(StrategyIndicator $indicator): ?float
    {
        return match ($indicator) {
            StrategyIndicator::POPULATION_GROWTH => $this->percentChange(
                StrategyIndicator::POPULATION_ESTIMATE,
                StrategyIndicator::POPULATION_CENSUS_2022,
            ),
            StrategyIndicator::DEMOGRAPHIC_DENSITY => $this->ratio(
                StrategyIndicator::POPULATION_CENSUS_2022,
                StrategyIndicator::TERRITORIAL_AREA,
            ),
            StrategyIndicator::PIB_PER_CAPITA => $this->weightedAverage(
                StrategyIndicator::PIB_PER_CAPITA,
                StrategyIndicator::POPULATION_ESTIMATE,
            ),
            StrategyIndicator::RELIGION_CATHOLIC_PCT_2022 => $this->percentage(StrategyIndicator::RELIGION_CATHOLIC_2022, StrategyIndicator::POPULATION_10_PLUS_2022),
            StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022 => $this->percentage(StrategyIndicator::RELIGION_EVANGELICAL_2022, StrategyIndicator::POPULATION_10_PLUS_2022),
            StrategyIndicator::RELIGION_NONE_PCT_2022 => $this->percentage(StrategyIndicator::RELIGION_NONE_2022, StrategyIndicator::POPULATION_10_PLUS_2022),
            StrategyIndicator::RELIGION_OTHER_PCT_2022 => $this->percentage(StrategyIndicator::RELIGION_OTHER_2022, StrategyIndicator::POPULATION_10_PLUS_2022),
            StrategyIndicator::RELIGION_EVANGELICAL_PCT_2010 => $this->percentage(StrategyIndicator::RELIGION_EVANGELICAL_2010, StrategyIndicator::POPULATION_2010),
            StrategyIndicator::RELIGION_BAPTIST_PCT_2010 => $this->percentage(StrategyIndicator::RELIGION_BAPTIST_2010, StrategyIndicator::POPULATION_2010),
            StrategyIndicator::RELIGION_EVANGELICAL_VARIATION => $this->difference(
                StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022,
                StrategyIndicator::RELIGION_EVANGELICAL_PCT_2010,
            ),
            default => $this->sum($indicator),
        };
    }

    /**
     * @return Collection<int, float>
     */
    private function values(StrategyIndicator $indicator): Collection
    {
        return collect($this->latestRows()[$indicator->value] ?? [])
            ->map(fn (MunicipalityIndicator $row): float => (float) $row->value);
    }

    private function sum(StrategyIndicator $indicator): ?float
    {
        $values = $this->values($indicator);

        return $values->isEmpty() ? null : round($values->sum(), 2);
    }

    private function rawRatio(StrategyIndicator $numerator, StrategyIndicator $denominator): ?float
    {
        $num = $this->sum($numerator);
        $den = $this->sum($denominator);

        return $num === null || $den === null || $den <= 0 ? null : $num / $den;
    }

    private function ratio(StrategyIndicator $numerator, StrategyIndicator $denominator): ?float
    {
        $ratio = $this->rawRatio($numerator, $denominator);

        return $ratio === null ? null : round($ratio, 2);
    }

    private function percentage(StrategyIndicator $numerator, StrategyIndicator $denominator): ?float
    {
        $ratio = $this->rawRatio($numerator, $denominator);

        return $ratio === null ? null : round($ratio * 100, 2);
    }

    private function percentChange(StrategyIndicator $current, StrategyIndicator $base): ?float
    {
        $ratio = $this->rawRatio($current, $base);

        return $ratio === null ? null : round(($ratio - 1) * 100, 2);
    }

    private function difference(StrategyIndicator $minuend, StrategyIndicator $subtrahend): ?float
    {
        $a = $this->stateValue($minuend);
        $b = $this->stateValue($subtrahend);

        return $a === null || $b === null ? null : round($a - $b, 2);
    }

    private function weightedAverage(StrategyIndicator $indicator, StrategyIndicator $weight): ?float
    {
        $values = $this->values($indicator);
        $weights = $this->values($weight)->only($values->keys()->all());

        $totalWeight = $weights->sum();

        if ($values->isEmpty() || $totalWeight <= 0) {
            return null;
        }

        $weighted = $weights->map(fn (float $w, int $id): float => $w * $values[$id])->sum();

        return round($weighted / $totalWeight, 2);
    }
}
