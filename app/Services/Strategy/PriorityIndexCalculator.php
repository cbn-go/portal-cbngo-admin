<?php

namespace App\Services\Strategy;

class PriorityIndexCalculator
{
    /**
     * Calcula o score de ausência de igrejas (0 a 100).
     * 0 igrejas => 100.0
     * n igrejas => round(100 / (1 + n), 2)
     */
    public function calculateAbsenceScore(int $activeChurches): float
    {
        if ($activeChurches <= 0) {
            return 100.0;
        }

        return round(100.0 / (1 + $activeChurches), 2);
    }

    /**
     * Calcula o índice de prioridade composto (0 a 100) e o detalhamento por fator.
     *
     * @param  ?float  $population  População de referência
     * @param  ?float  $growth  Taxa de crescimento (%)
     * @param  ?float  $nonEvangelicalPct  Percentual de não evangélicos (0 a 100)
     * @param  int  $activeChurches  Contagem de igrejas ativas
     * @param  array{min_pop?: float, max_pop?: float, min_growth?: float, max_growth?: float}  $bounds  Limites para normalização
     * @return array{score: ?float, breakdown: array{population: ?float, growth: ?float, non_evangelical: ?float, church_absence: float}}
     */
    public function calculate(
        ?float $population,
        ?float $growth,
        ?float $nonEvangelicalPct,
        int $activeChurches,
        array $bounds = [],
    ): array {
        /** @var array<string, float> $weights */
        $weights = config('strategy.priority_index.weights', [
            'population' => 0.30,
            'growth' => 0.20,
            'non_evangelical_pct' => 0.25,
            'church_absence' => 0.25,
        ]);

        $missingStrategy = config('strategy.priority_index.missing_data_strategy', 'reweight_available');

        // 1. Normalização da População (0 a 100)
        $normPop = null;
        if ($population !== null) {
            $minPop = (float) ($bounds['min_pop'] ?? 0.0);
            $maxPop = (float) ($bounds['max_pop'] ?? $population);

            if ($maxPop > $minPop) {
                $normPop = round((($population - $minPop) / ($maxPop - $minPop)) * 100.0, 2);
            } else {
                $normPop = 50.0;
            }
            $normPop = max(0.0, min(100.0, $normPop));
        }

        // 2. Normalização do Crescimento (0 a 100)
        $normGrowth = null;
        if ($growth !== null) {
            $minGrowth = (float) ($bounds['min_growth'] ?? 0.0);
            $maxGrowth = (float) ($bounds['max_growth'] ?? $growth);

            if ($maxGrowth > $minGrowth) {
                $normGrowth = round((($growth - $minGrowth) / ($maxGrowth - $minGrowth)) * 100.0, 2);
            } else {
                $normGrowth = 50.0;
            }
            $normGrowth = max(0.0, min(100.0, $normGrowth));
        }

        // 3. Normalização do % Não Evangélicos (0 a 100)
        $normNonEv = null;
        if ($nonEvangelicalPct !== null) {
            $normNonEv = round(max(0.0, min(100.0, $nonEvangelicalPct)), 2);
        }

        // 4. Ausência de Igreja (0 a 100)
        $normAbsence = $this->calculateAbsenceScore($activeChurches);

        // Agrupamento dos fatores com seus pesos
        $factors = [
            'population' => ['val' => $normPop, 'weight' => (float) ($weights['population'] ?? 0.30)],
            'growth' => ['val' => $normGrowth, 'weight' => (float) ($weights['growth'] ?? 0.20)],
            'non_evangelical' => ['val' => $normNonEv, 'weight' => (float) ($weights['non_evangelical_pct'] ?? 0.25)],
            'church_absence' => ['val' => $normAbsence, 'weight' => (float) ($weights['church_absence'] ?? 0.25)],
        ];

        $totalScore = 0.0;
        $totalWeight = 0.0;

        foreach ($factors as $factor) {
            if ($factor['val'] !== null) {
                $totalScore += $factor['val'] * $factor['weight'];
                $totalWeight += $factor['weight'];
            } elseif ($missingStrategy === 'zero_fill') {
                $totalWeight += $factor['weight'];
            }
        }

        $finalScore = null;
        if ($totalWeight > 0) {
            $finalScore = round($totalScore / $totalWeight, 2);
            $finalScore = max(0.0, min(100.0, $finalScore));
        }

        return [
            'score' => $finalScore,
            'breakdown' => [
                'population' => $normPop,
                'growth' => $normGrowth,
                'non_evangelical' => $normNonEv,
                'church_absence' => $normAbsence,
            ],
        ];
    }

    /**
     * Comparador determinístico para ordenação por prioridade com critérios de desempate.
     *
     * @param  object|array<string, mixed>  $a
     * @param  object|array<string, mixed>  $b
     */
    public function comparePriorities(object|array $a, object|array $b): int
    {
        $aScore = is_array($a) ? ($a['priority_index'] ?? null) : ($a->priority_index ?? null);
        $bScore = is_array($b) ? ($b['priority_index'] ?? null) : ($b->priority_index ?? null);

        // 1. Maior priority_index primeiro
        if ($aScore !== $bScore) {
            return $bScore <=> $aScore;
        }

        // 2. Desempate: maior população primeiro
        $aPop = is_array($a) ? ($a['population'] ?? 0) : ($a->population ?? 0);
        $bPop = is_array($b) ? ($b['population'] ?? 0) : ($b->population ?? 0);
        if ($aPop !== $bPop) {
            return $bPop <=> $aPop;
        }

        // 3. Desempate: menor contagem de igrejas (sem igreja primeiro)
        $aChurches = is_array($a) ? ($a['active_churches_count'] ?? 0) : ($a->active_churches_count ?? 0);
        $bChurches = is_array($b) ? ($b['active_churches_count'] ?? 0) : ($b->active_churches_count ?? 0);
        if ($aChurches !== $bChurches) {
            return $aChurches <=> $bChurches;
        }

        // 4. Desempate alfabético pelo nome
        $aName = is_array($a) ? ($a['name'] ?? '') : ($a->name ?? '');
        $bName = is_array($b) ? ($b['name'] ?? '') : ($b->name ?? '');

        return strcmp($aName, $bName);
    }
}
