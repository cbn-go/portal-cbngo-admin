<?php

namespace Tests\Unit\Services\Strategy;

use App\Services\Strategy\PriorityIndexCalculator;
use Tests\TestCase;

class PriorityIndexCalculatorTest extends TestCase
{
    protected PriorityIndexCalculator $calculator;

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

        $this->calculator = new PriorityIndexCalculator;
    }

    public function test_absence_score_follows_inverse_proportional_decay(): void
    {
        $this->assertSame(100.0, $this->calculator->calculateAbsenceScore(0));
        $this->assertSame(50.0, $this->calculator->calculateAbsenceScore(1));
        $this->assertSame(33.33, $this->calculator->calculateAbsenceScore(2));
        $this->assertSame(25.0, $this->calculator->calculateAbsenceScore(3));
        $this->assertSame(16.67, $this->calculator->calculateAbsenceScore(5));
    }

    public function test_calculates_composite_priority_index_within_0_to_100_range(): void
    {
        $bounds = [
            'min_pop' => 1000.0,
            'max_pop' => 100000.0,
            'min_growth' => -10.0,
            'max_growth' => 30.0,
        ];

        // City A: Average municipality with no church
        $resultNoChurch = $this->calculator->calculate(
            population: 50500.0,
            growth: 10.0,
            nonEvangelicalPct: 70.0,
            activeChurches: 0,
            bounds: $bounds,
        );

        $this->assertGreaterThanOrEqual(0.0, $resultNoChurch['score']);
        $this->assertLessThanOrEqual(100.0, $resultNoChurch['score']);
        $this->assertSame(100.0, $resultNoChurch['breakdown']['church_absence']);

        // City B: Same municipality but with 3 churches
        $resultWithChurches = $this->calculator->calculate(
            population: 50500.0,
            growth: 10.0,
            nonEvangelicalPct: 70.0,
            activeChurches: 3,
            bounds: $bounds,
        );

        $this->assertLessThan($resultNoChurch['score'], $resultWithChurches['score']);
        $this->assertSame(25.0, $resultWithChurches['breakdown']['church_absence']);
    }

    public function test_municipality_with_several_churches_has_progressively_lower_score(): void
    {
        $bounds = [
            'min_pop' => 1000.0,
            'max_pop' => 100000.0,
            'min_growth' => 0.0,
            'max_growth' => 20.0,
        ];

        $scores = [];
        foreach ([0, 1, 2, 5, 10] as $churches) {
            $res = $this->calculator->calculate(
                population: 20000.0,
                growth: 10.0,
                nonEvangelicalPct: 65.0,
                activeChurches: $churches,
                bounds: $bounds,
            );
            $scores[$churches] = $res['score'];
        }

        $this->assertGreaterThan($scores[1], $scores[0]);
        $this->assertGreaterThan($scores[2], $scores[1]);
        $this->assertGreaterThan($scores[5], $scores[2]);
        $this->assertGreaterThan($scores[10], $scores[5]);
    }

    public function test_handles_missing_ibge_data_by_reweighting_available_indicators(): void
    {
        $bounds = [
            'min_pop' => 1000.0,
            'max_pop' => 100000.0,
            'min_growth' => 0.0,
            'max_growth' => 20.0,
        ];

        // Growth and religious profile are null
        $result = $this->calculator->calculate(
            population: 100000.0, // max population -> normalized = 100
            growth: null,
            nonEvangelicalPct: null,
            activeChurches: 0, // 0 churches -> normalized = 100
            bounds: $bounds,
        );

        // Available: population (0.30) and absence (0.25)
        // Both are 100, reweighted should be 100.0
        $this->assertSame(100.0, $result['score']);
        $this->assertNull($result['breakdown']['growth']);
        $this->assertNull($result['breakdown']['non_evangelical']);
        $this->assertSame(100.0, $result['breakdown']['population']);
        $this->assertSame(100.0, $result['breakdown']['church_absence']);
    }

    public function test_handles_boundary_clipping_and_zero_range_bounds_safely(): void
    {
        $singleBound = [
            'min_pop' => 5000.0,
            'max_pop' => 5000.0,
            'min_growth' => 5.0,
            'max_growth' => 5.0,
        ];

        $res = $this->calculator->calculate(
            population: 5000.0,
            growth: 5.0,
            nonEvangelicalPct: 50.0,
            activeChurches: 0,
            bounds: $singleBound,
        );

        $this->assertNotNull($res['score']);
        $this->assertGreaterThanOrEqual(0.0, $res['score']);
        $this->assertLessThanOrEqual(100.0, $res['score']);
    }

    public function test_tie_breaking_sorts_deterministically(): void
    {
        $items = [
            (object) ['id' => 1, 'name' => 'Bela Vista', 'priority_index' => 75.0, 'population' => 30000, 'active_churches_count' => 1],
            (object) ['id' => 2, 'name' => 'Anápolis', 'priority_index' => 85.0, 'population' => 400000, 'active_churches_count' => 5],
            (object) ['id' => 3, 'name' => 'Catalão', 'priority_index' => 75.0, 'population' => 50000, 'active_churches_count' => 1],
            (object) ['id' => 4, 'name' => 'Abadiânia', 'priority_index' => 75.0, 'population' => 50000, 'active_churches_count' => 0],
        ];

        usort($items, [$this->calculator, 'comparePriorities']);

        $this->assertSame('Anápolis', $items[0]->name);
        $this->assertSame('Abadiânia', $items[1]->name);
        $this->assertSame('Catalão', $items[2]->name);
        $this->assertSame('Bela Vista', $items[3]->name);
    }
}
