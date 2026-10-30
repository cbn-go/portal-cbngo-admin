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
        $this->fakeAllIbgeEndpoints();

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
            'https://servicodados.ibge.gov.br/api/v3/agregados/4714/*' => Http::response($fixture9514, 200),
            'https://servicodados.ibge.gov.br/api/v3/agregados/9514/*' => Http::response($fixture9514, 200),
            '*' => Http::response([], 200),
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
            'https://servicodados.ibge.gov.br/api/v3/agregados/4714/*' => Http::response($fixture9514, 200),
            'https://servicodados.ibge.gov.br/api/v3/agregados/9514/*' => Http::response($fixture9514, 200),
            '*' => Http::response([], 200),
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
            '*' => Http::response(['error' => 'Server Error'], 500),
        ]);

        $this->artisan('strategy:sync-indicators')
            ->assertSuccessful();

        $existing->refresh();
        $this->assertSame('12345.00', (string) $existing->value);
        $this->assertSame(1, MunicipalityIndicator::query()->count());
    }

    public function test_sync_indicators_command_syncs_all_demographic_and_socioeconomic_indicators(): void
    {
        $this->fakeAllIbgeEndpoints();

        $this->artisan('strategy:sync-indicators')
            ->assertSuccessful();

        // 246 municipalities * 14 active indicators = 3444 indicators
        $activeIndicators = [
            StrategyIndicator::POPULATION_CENSUS_2022,
            StrategyIndicator::POPULATION_ESTIMATE,
            StrategyIndicator::POPULATION_GROWTH,
            StrategyIndicator::AGE_0_14,
            StrategyIndicator::AGE_15_29,
            StrategyIndicator::AGE_30_59,
            StrategyIndicator::AGE_60_PLUS,
            StrategyIndicator::RACE_WHITE,
            StrategyIndicator::RACE_BLACK,
            StrategyIndicator::RACE_YELLOW,
            StrategyIndicator::RACE_BROWN,
            StrategyIndicator::RACE_INDIGENOUS,
            StrategyIndicator::TERRITORIAL_AREA,
            StrategyIndicator::DEMOGRAPHIC_DENSITY,
            StrategyIndicator::PIB_PER_CAPITA,
        ];

        foreach ($activeIndicators as $indicator) {
            $count = MunicipalityIndicator::where('indicator', $indicator)->count();
            $this->assertSame(246, $count, "Falha na contagem do indicador {$indicator->value}");
        }

        // Check specific municipality values for Abadia de Goiás (5200050)
        $abadia = Municipality::where('ibge_code', '5200050')->firstOrFail();

        $pop2022 = MunicipalityIndicator::where('municipality_id', $abadia->id)
            ->where('indicator', StrategyIndicator::POPULATION_CENSUS_2022)->firstOrFail();
        $this->assertSame('19128.00', (string) $pop2022->value);

        $popEst = MunicipalityIndicator::where('municipality_id', $abadia->id)
            ->where('indicator', StrategyIndicator::POPULATION_ESTIMATE)->firstOrFail();
        $this->assertSame('22918.00', (string) $popEst->value);

        $area = MunicipalityIndicator::where('municipality_id', $abadia->id)
            ->where('indicator', StrategyIndicator::TERRITORIAL_AREA)->firstOrFail();
        $this->assertSame('143.36', (string) $area->value);

        $pibPerCapita = MunicipalityIndicator::where('municipality_id', $abadia->id)
            ->where('indicator', StrategyIndicator::PIB_PER_CAPITA)->firstOrFail();
        $this->assertSame('24988.67', (string) $pibPerCapita->value);
    }

    public function test_sync_indicators_command_calculates_growth_and_density_at_the_end_of_sync(): void
    {
        $this->fakeAllIbgeEndpoints();

        $this->artisan('strategy:sync-indicators')
            ->assertSuccessful();

        $abadia = Municipality::where('ibge_code', '5200050')->firstOrFail();

        // Pop Censo 2022 = 19128, Pop Estimada = 22918
        // Growth % = ((22918 - 19128) / 19128) * 100 = (3790 / 19128) * 100 = 19.8138... -> 19.81%
        $growth = MunicipalityIndicator::where('municipality_id', $abadia->id)
            ->where('indicator', StrategyIndicator::POPULATION_GROWTH)->firstOrFail();
        $this->assertSame('19.81', (string) $growth->value);
        $this->assertSame('Calculado (Censo 2022 → Estimativa recente)', $growth->source);

        // Density = 19128 / 143.357 = 133.429... -> 133.43 hab/km²
        $density = MunicipalityIndicator::where('municipality_id', $abadia->id)
            ->where('indicator', StrategyIndicator::DEMOGRAPHIC_DENSITY)->firstOrFail();
        $this->assertSame('133.43', (string) $density->value);
        $this->assertSame('Calculado (População / Área)', $density->source);
    }

    public function test_sync_indicators_command_cross_checks_state_totals_against_ibge_published_values(): void
    {
        $this->fakeAllIbgeEndpoints();

        $this->artisan('strategy:sync-indicators')
            ->expectsOutputToContain('Conferência de Totais de Goiás (IBGE N3[52])')
            ->assertSuccessful();

        // Sum of all 246 municipalities for POPULATION_CENSUS_2022 equals 7056495
        $sumPop2022 = MunicipalityIndicator::where('indicator', StrategyIndicator::POPULATION_CENSUS_2022)->sum('value');
        $this->assertEquals(7056495, (int) $sumPop2022);

        // Sum of all 246 municipalities for POPULATION_ESTIMATE equals 7495033
        $sumPopEst = MunicipalityIndicator::where('indicator', StrategyIndicator::POPULATION_ESTIMATE)->sum('value');
        $this->assertEquals(7495033, (int) $sumPopEst);

        // Sum of all 246 municipalities for TERRITORIAL_AREA is 340242.86 km² (rounding tolerance <= 0.001%)
        $sumArea = MunicipalityIndicator::where('indicator', StrategyIndicator::TERRITORIAL_AREA)->sum('value');
        $this->assertEqualsWithDelta(340242.86, $sumArea, 1.0);
    }

    public function test_handles_zero_area_or_population_gracefully_in_calculated_indicators(): void
    {
        $this->fakeAllIbgeEndpoints();

        // Simulate a municipality having 0 area
        $fixtureArea = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_4714_area_territorial.json')), true);
        $fixtureArea[0]['resultados'][0]['series'][0]['serie']['2022'] = '0';

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/4714/periodos/2022/variaveis/6318*localidades=N6*' => Http::response($fixtureArea, 200),
            '*' => Http::response([], 200),
        ]);

        $this->fakeAllIbgeEndpoints();
        // Overwrite area with 0
        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/4714/periodos/2022/variaveis/6318*localidades=N6*' => Http::response($fixtureArea, 200),
        ]);

        // Command should succeed and not throw DivisionByZeroError
        $this->artisan('strategy:sync-indicators')
            ->assertSuccessful();
    }

    protected function fakeAllIbgeEndpoints(): void
    {
        $f4714Pop = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_4714_populacao_2022.json')), true);
        $f4714PopState = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_4714_populacao_2022_estado.json')), true);
        $f4714Area = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_4714_area_territorial.json')), true);
        $f4714AreaState = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_4714_area_territorial_estado.json')), true);
        $f6579 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_6579_estimativa.json')), true);
        $f6579State = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_6579_estimativa_estado.json')), true);

        $fIdade014 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_0_14.json')), true);
        $fIdade014State = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_0_14_estado.json')), true);
        $fIdade1529 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_15_29.json')), true);
        $fIdade1529State = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_15_29_estado.json')), true);
        $fIdade3059 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_30_59.json')), true);
        $fIdade3059State = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_30_59_estado.json')), true);
        $fIdade60 = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_60_mais.json')), true);
        $fIdade60State = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_60_mais_estado.json')), true);

        $fRacaBranca = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_branca.json')), true);
        $fRacaBrancaState = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_branca_estado.json')), true);
        $fRacaPreta = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_preta.json')), true);
        $fRacaPretaState = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_preta_estado.json')), true);
        $fRacaAmarela = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_amarela.json')), true);
        $fRacaAmarelaState = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_amarela_estado.json')), true);
        $fRacaParda = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_parda.json')), true);
        $fRacaPardaState = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_parda_estado.json')), true);
        $fRacaIndigena = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_indigena.json')), true);
        $fRacaIndigenaState = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9606_raca_indigena_estado.json')), true);

        $fPibPerCapita = json_decode((string) file_get_contents(base_path('tests/Fixtures/ibge/ibge_cidades_pib_per_capita.json')), true);

        Http::fake(function ($request) use (
            $f4714Pop, $f4714PopState, $f4714Area, $f4714AreaState,
            $f6579, $f6579State,
            $fIdade014, $fIdade014State, $fIdade1529, $fIdade1529State,
            $fIdade3059, $fIdade3059State, $fIdade60, $fIdade60State,
            $fRacaBranca, $fRacaBrancaState, $fRacaPreta, $fRacaPretaState,
            $fRacaAmarela, $fRacaAmarelaState, $fRacaParda, $fRacaPardaState,
            $fRacaIndigena, $fRacaIndigenaState,
            $fPibPerCapita
        ) {
            $url = $request->url();

            if (str_contains($url, 'pesquisas/38/indicadores/47001/resultados')) {
                return Http::response($fPibPerCapita, 200);
            }

            $isState = str_contains($url, 'localidades=N3%5B52%5D') || str_contains($url, 'localidades=N3[52]');

            if (str_contains($url, 'agregados/4714')) {
                if (str_contains($url, 'variaveis/93')) {
                    return Http::response($isState ? $f4714PopState : $f4714Pop, 200);
                }
                if (str_contains($url, 'variaveis/6318')) {
                    return Http::response($isState ? $f4714AreaState : $f4714Area, 200);
                }
            }

            if (str_contains($url, 'agregados/6579')) {
                return Http::response($isState ? $f6579State : $f6579, 200);
            }

            if (str_contains($url, 'agregados/9514')) {
                if (str_contains($url, '93070')) {
                    return Http::response($isState ? $fIdade014State : $fIdade014, 200);
                }
                if (str_contains($url, '93086')) {
                    return Http::response($isState ? $fIdade1529State : $fIdade1529, 200);
                }
                if (str_contains($url, '93089')) {
                    return Http::response($isState ? $fIdade3059State : $fIdade3059, 200);
                }
                if (str_contains($url, '93095')) {
                    return Http::response($isState ? $fIdade60State : $fIdade60, 200);
                }
            }

            if (str_contains($url, 'agregados/9606')) {
                if (str_contains($url, '2776')) {
                    return Http::response($isState ? $fRacaBrancaState : $fRacaBranca, 200);
                }
                if (str_contains($url, '2777')) {
                    return Http::response($isState ? $fRacaPretaState : $fRacaPreta, 200);
                }
                if (str_contains($url, '2778')) {
                    return Http::response($isState ? $fRacaAmarelaState : $fRacaAmarela, 200);
                }
                if (str_contains($url, '2779')) {
                    return Http::response($isState ? $fRacaPardaState : $fRacaParda, 200);
                }
                if (str_contains($url, '2780')) {
                    return Http::response($isState ? $fRacaIndigenaState : $fRacaIndigena, 200);
                }
            }

            return Http::response([], 200);
        });
    }
}
