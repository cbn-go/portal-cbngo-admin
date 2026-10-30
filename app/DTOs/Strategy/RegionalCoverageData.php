<?php

namespace App\DTOs\Strategy;

final readonly class RegionalCoverageData
{
    public function __construct(
        public string $regionType, // 'immediate' | 'intermediate' | 'state'
        public string $regionCode,
        public string $regionName,
        public int $totalMunicipalitiesCount,
        public int $municipalitiesWithChurchCount,
        public int $municipalitiesWithoutChurchCount,
        public float $coveragePercentage,
        public int $activeChurchesCount,
        public int $totalPopulation,
        public int $totalNonEvangelicalPopulation,
        public ?float $inhabitantsPerChurch,
        public ?float $nonEvangelicalsPerChurch,
        public int $publishedSocialProjectsCount,
    ) {}
}

