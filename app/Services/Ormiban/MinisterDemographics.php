<?php

namespace App\Services\Ormiban;

use App\DTOs\Ormiban\MinisterDemographicsData;
use App\Models\Minister;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Faixas de idade e de ministério do rol.
 *
 * A referência é o início do dia de hoje no fuso config('app.timezone').
 * O tempo de ministério usa a ordenação mais antiga (código 2).
 */
class MinisterDemographics
{
    /** @var array<string, MinisterDemographicsData> */
    private array $cache = [];

    /**
     * @param  array<string, mixed>  $filters
     */
    public function summarize(array $filters): MinisterDemographicsData
    {
        $function = $this->filterValue($filters['function'] ?? null);
        $status = $this->filterValue($filters['status'] ?? null);
        $key = ($function ?? '').'|'.($status ?? '');

        return $this->cache[$key] ??= $this->calculate($function, $status);
    }

    private function calculate(?string $function, ?string $status): MinisterDemographicsData
    {
        $ministers = Minister::query()
            ->when($function !== null, fn (Builder $query): Builder => $query->where('function', $function))
            ->when($status !== null, fn (Builder $query): Builder => $query->where('status', $status))
            ->withMin([
                'historyEvents as ordination_on' => fn (Builder $query): Builder => $query->where('code', '2'),
            ], 'occurred_on')
            ->get();

        $ageBands = $this->emptyBands('age_bands');
        $ministryBands = $this->emptyBands('ministry_bands');
        $missingOrdination = (string) config('ormiban.missing_ordination_label');
        $ministryBands[$missingOrdination] = 0;

        $ages = [];
        $withOrdination = 0;
        $activeCount = 0;
        $pastorCount = 0;
        $missionaryCount = 0;

        foreach ($ministers as $minister) {
            if ($minister->status === Minister::STATUS_ACTIVE) {
                $activeCount++;
            }

            if ($minister->function === 'Pastor') {
                $pastorCount++;
            } elseif ($minister->function === 'Missionário') {
                $missionaryCount++;
            }

            $age = $this->completedYears($minister->birth_date);
            if ($age !== null) {
                $ages[] = $age;
                $label = $this->bandLabel($age, 'age_bands');
                if ($label !== null) {
                    $ageBands[$label]++;
                }
            }

            $ministryYears = $this->completedYears($minister->getAttribute('ordination_on'));
            if ($ministryYears === null) {
                $ministryBands[$missingOrdination]++;
            } else {
                $withOrdination++;
                $label = $this->bandLabel($ministryYears, 'ministry_bands');
                if ($label !== null) {
                    $ministryBands[$label]++;
                }
            }
        }

        $total = $ministers->count();

        return new MinisterDemographicsData(
            total: $total,
            activeCount: $activeCount,
            pastorCount: $pastorCount,
            missionaryCount: $missionaryCount,
            ageMean: $this->mean($ages),
            ageMedian: $this->median($ages),
            ordinationPercent: $this->percent($withOrdination, $total),
            ageBands: $ageBands,
            ministryBands: $ministryBands,
        );
    }

    private function filterValue(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return $value;
    }

    /**
     * Anos completos até o início de hoje. Data ausente ou futura não entra.
     */
    private function completedYears(mixed $date): ?int
    {
        if ($date === null || $date === '') {
            return null;
        }

        $timezone = (string) config('app.timezone');
        $start = Carbon::parse($date, $timezone)->startOfDay();
        $reference = now()->timezone($timezone)->startOfDay();
        $years = $start->diffInYears($reference, false);

        if ($years < 0) {
            return null;
        }

        return (int) floor($years);
    }

    /**
     * @return array<string, int>
     */
    private function emptyBands(string $configKey): array
    {
        $counts = [];

        foreach ($this->bands($configKey) as $band) {
            $counts[$band['label']] = 0;
        }

        return $counts;
    }

    private function bandLabel(int $years, string $configKey): ?string
    {
        foreach ($this->bands($configKey) as $band) {
            if ($band['min'] !== null && $years < $band['min']) {
                continue;
            }

            if ($band['max'] !== null && $years > $band['max']) {
                continue;
            }

            return $band['label'];
        }

        return null;
    }

    /**
     * @return list<array{label: string, min: ?int, max: ?int}>
     */
    private function bands(string $configKey): array
    {
        /** @var mixed $configured */
        $configured = config('ormiban.'.$configKey, []);

        if (! is_array($configured)) {
            return [];
        }

        $bands = [];

        foreach ($configured as $band) {
            if (! is_array($band) || ! isset($band['label']) || ! is_string($band['label'])) {
                continue;
            }

            $bands[] = [
                'label' => $band['label'],
                'min' => isset($band['min']) ? (int) $band['min'] : null,
                'max' => isset($band['max']) ? (int) $band['max'] : null,
            ];
        }

        return $bands;
    }

    /**
     * @param  list<int>  $values
     */
    private function mean(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        return round(array_sum($values) / count($values), 1);
    }

    /**
     * @param  list<int>  $values
     */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return round((float) $values[$middle], 1);
        }

        return round(($values[$middle - 1] + $values[$middle]) / 2, 1);
    }

    private function percent(int $part, int $total): ?float
    {
        if ($total === 0) {
            return null;
        }

        return round($part / $total * 100, 1);
    }
}
