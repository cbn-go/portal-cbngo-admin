<?php

namespace Tests\Feature\Console;

use App\Enums\StrategyIndicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicator;
use Database\Seeders\MunicipalitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncStrategyIndicatorsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MunicipalitySeeder::class);
    }

    public function test_sync_indicators_command_persists_indicators_and_is_idempotent(): void
    {
        $fixture9514 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_populacao_2022.json')), true);
        $fixture6579 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_6579_estimativa.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/9514/*' => Http::response($fixture9514, 200),
            'https://servicodados.ibge.gov.br/api/v3/agregados/6579/*' => Http::response($fixture6579, 200),
        ]);

        $this->artisan('strategy:sync-indicators')
            ->assertSuccessful();

        $count = MunicipalityIndicator::query()->count();
        $this->assertGreaterThan(0, $count);

        $abadia = Municipality::where('ibge_code', '5200050')->firstOrFail();
        $indicator = MunicipalityIndicator::query()
            ->where('municipality_id', $abadia->id)
            ->where('indicator', StrategyIndicator::POPULATION_CENSUS_2022)
            ->where('period', '2022')
            ->first();

        $this->assertNotNull($indicator);
        $this->assertSame('19128.00', (string) $indicator->value);
        $this->assertSame(StrategyIndicator::POPULATION_CENSUS_2022, $indicator->indicator);
        $this->assertNotNull($indicator->source);

        // Run second time to verify idempotency
        $this->artisan('strategy:sync-indicators')
            ->assertSuccessful();

        $this->assertSame($count, MunicipalityIndicator::query()->count());
    }

    public function test_sync_indicators_command_supports_indicator_filter(): void
    {
        $fixture9514 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_populacao_2022.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/9514/*' => Http::response($fixture9514, 200),
        ]);

        $this->artisan('strategy:sync-indicators', [
            '--indicator' => 'population_census_2022',
        ])->assertSuccessful();

        $this->assertSame(246, MunicipalityIndicator::query()->count());
        $this->assertTrue(MunicipalityIndicator::where('indicator', StrategyIndicator::POPULATION_CENSUS_2022)->exists());
        $this->assertFalse(MunicipalityIndicator::where('indicator', StrategyIndicator::POPULATION_ESTIMATE)->exists());
    }

    public function test_sync_indicators_command_supports_dry_run_option(): void
    {
        $fixture9514 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_populacao_2022.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/9514/*' => Http::response($fixture9514, 200),
        ]);

        $this->artisan('strategy:sync-indicators', [
            '--indicator' => 'population_census_2022',
            '--dry-run' => true,
        ])->assertSuccessful();

        $this->assertSame(0, MunicipalityIndicator::query()->count());
    }

    public function test_sync_indicators_command_does_not_erase_existing_data_on_api_failure(): void
    {
        $municipality = Municipality::firstOrFail();

        $existing = MunicipalityIndicator::create([
            'municipality_id' => $municipality->id,
            'indicator' => StrategyIndicator::POPULATION_CENSUS_2022,
            'period' => '2022',
            'value' => 12345.00,
            'source' => 'IBGE/SIDRA 9514',
            'fetched_at' => now()->subDay(),
        ]);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $this->artisan('strategy:sync-indicators')
            ->assertSuccessful();

        $existing->refresh();
        $this->assertSame('12345.00', (string) $existing->value);
        $this->assertSame(1, MunicipalityIndicator::query()->count());
    }
}
