<?php

namespace App\DTOs\Strategy;

final readonly class MunicipalityCoverageData
{
    /**
     * @param  array<string, ?float>  $priorityBreakdown
     */
    public function __construct(
        public int $municipalityId,
        public string $ibgeCode,
        public string $name,
        public string $immediateRegionCode,
        public string $immediateRegionName,
        public string $intermediateRegionCode,
        public string $intermediateRegionName,
        public int $activeChurchesCount,
        public ?int $population,
        public ?float $inhabitantsPerChurch,
        public ?int $nonEvangelicalPopulation,
        public ?float $nonEvangelicalsPerChurch,
        public int $publishedSocialProjectsCount,
        public ?float $priorityIndex,
        public array $priorityBreakdown = [],
    ) {}
}
