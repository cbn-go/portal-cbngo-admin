<?php

namespace Tests\Unit\Services\Ormiban;

use App\Models\Minister;
use App\Services\Ormiban\MinisterDemographics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class MinisterDemographicsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-11-04', 'America/Sao_Paulo'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_places_completed_years_on_band_boundaries(): void
    {
        $this->minister('Pastor', '1997-11-04');
        $this->minister('Pastor', '1996-11-05');
        $this->minister('Pastor', '1996-11-04');
        $this->minister('Pastor', null);

        $data = app(MinisterDemographics::class)->summarize([]);

        $this->assertSame(2, $data->ageBands['Menos de 30']);
        $this->assertSame(1, $data->ageBands['30–39']);
        $this->assertSame(29.3, $data->ageMean);
        $this->assertSame(29.0, $data->ageMedian);
        $this->assertSame(4, $data->ministryBands['Sem ordenação']);
    }

    public function test_ministry_uses_the_oldest_ordination_and_ignores_diploma(): void
    {
        $minister = $this->minister('Pastor', '1980-01-01');
        $minister->historyEvents()->create([
            'occurred_on' => '2016-11-04',
            'code' => '2',
            'description' => 'ORDENAÇÃO recente',
        ]);
        $minister->historyEvents()->create([
            'occurred_on' => '1990-11-04',
            'code' => '2',
            'description' => 'ORDENAÇÃO',
        ]);
        $minister->historyEvents()->create([
            'occurred_on' => '1980-11-04',
            'code' => '1',
            'description' => 'DIPLOMADO',
        ]);

        $withoutOrdination = $this->minister('Missionário', '1970-11-04');
        $withoutOrdination->historyEvents()->create([
            'occurred_on' => '1986-11-04',
            'code' => '1',
            'description' => 'DIPLOMADO',
        ]);

        $data = app(MinisterDemographics::class)->summarize([]);

        $this->assertSame(0, $data->ministryBands['Menos de 30']);
        $this->assertSame(1, $data->ministryBands['30–39']);
        $this->assertSame(1, $data->ministryBands['Sem ordenação']);
        $this->assertArrayNotHasKey('Sem diplomação', $data->ministryBands);
        $this->assertSame(50.0, $data->ordinationPercent);
    }

    public function test_function_and_status_filters_change_counts(): void
    {
        $this->minister('Pastor', '1986-11-04', Minister::STATUS_ACTIVE);
        $this->minister('Missionário', '1976-11-04', Minister::STATUS_ACTIVE);
        $this->minister('Pastor', '1976-11-04', Minister::STATUS_INACTIVE);

        $service = app(MinisterDemographics::class);
        $pastors = $service->summarize(['function' => 'Pastor']);
        $inactive = $service->summarize(['status' => Minister::STATUS_INACTIVE]);

        $this->assertSame(2, $pastors->total);
        $this->assertSame(2, $pastors->pastorCount);
        $this->assertSame(0, $pastors->missionaryCount);
        $this->assertSame(1, $pastors->activeCount);
        $this->assertSame(1, $inactive->total);
        $this->assertSame(0, $inactive->activeCount);
        $this->assertSame(1, $inactive->ageBands['50–59']);
    }

    public function test_median_of_even_count_averages_the_two_central_ages(): void
    {
        $this->minister('Pastor', '1996-11-04');
        $this->minister('Pastor', '1986-11-04');

        $data = app(MinisterDemographics::class)->summarize([]);

        $this->assertSame(35.0, $data->ageMean);
        $this->assertSame(35.0, $data->ageMedian);
    }

    public function test_empty_roll_has_no_average_or_percentage(): void
    {
        $data = app(MinisterDemographics::class)->summarize([]);

        $this->assertSame(0, $data->total);
        $this->assertSame(0, $data->activeCount);
        $this->assertNull($data->ageMean);
        $this->assertNull($data->ageMedian);
        $this->assertNull($data->ordinationPercent);
        $this->assertSame(0, $data->ministryBands['Sem ordenação']);
        $this->assertArrayNotHasKey('Sem diplomação', $data->ministryBands);
    }

    private function minister(string $function, ?string $birthDate, string $status = Minister::STATUS_ACTIVE): Minister
    {
        return Minister::factory()->create([
            'function' => $function,
            'birth_date' => $birthDate,
            'status' => $status,
        ]);
    }
}
