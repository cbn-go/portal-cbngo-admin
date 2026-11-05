<?php

namespace App\DTOs\Ormiban;

final readonly class MinisterDemographicsData
{
    /**
     * @param  array<string, int>  $ageBands
     * @param  array<string, int>  $ministryBands
     * @param  array<string, int>  $ordinationBands
     */
    public function __construct(
        public int $total,
        public int $activeCount,
        public int $pastorCount,
        public int $missionaryCount,
        public ?float $ageMean,
        public ?float $ageMedian,
        public ?float $diplomaPercent,
        public ?float $ordinationPercent,
        public array $ageBands,
        public array $ministryBands,
        public array $ordinationBands,
    ) {}
}
