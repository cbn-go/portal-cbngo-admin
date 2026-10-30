<?php

namespace Tests\Unit\Support;

use App\Support\Ibge\SidraClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SidraClientTest extends TestCase
{
    public function test_fetches_and_parses_municipality_values_from_sidra_api(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_populacao_2022.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/9514/periodos/2022/variaveis/93*' => Http::response($fixture, 200),
        ]);

        $client = new SidraClient;
        $results = $client->fetchMunicipalityValues(9514, '2022', 93);

        $this->assertCount(246, $results);

        $first = $results[0];
        $this->assertSame('5200050', $first['ibge_code']);
        $this->assertSame('2022', $first['period']);
        $this->assertSame(19128.0, $first['value']);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'localidades=N6%5BN3%5B52%5D%5D')
                || str_contains($request->url(), 'localidades=N6[N3[52]]');
        });
    }

    public function test_handles_latest_period_minus_one_and_extracts_actual_year(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_6579_estimativa.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/6579/periodos/-1/variaveis/9324*' => Http::response($fixture, 200),
        ]);

        $client = new SidraClient;
        $results = $client->fetchMunicipalityValues(6579, '-1', 9324);

        $this->assertCount(246, $results);

        $first = $results[0];
        $this->assertSame('5200050', $first['ibge_code']);
        $this->assertSame('2026', $first['period']);
        $this->assertSame(22918.0, $first['value']);
    }

    public function test_retries_on_server_error_and_succeeds(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_populacao_2022.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/*' => Http::sequence()
                ->push(['error' => 'Internal Server Error'], 500)
                ->push($fixture, 200),
        ]);

        $client = new SidraClient;
        $results = $client->fetchMunicipalityValues(9514, '2022', 93);

        $this->assertCount(246, $results);
    }

    public function test_handles_omission_or_non_numeric_values_gracefully(): void
    {
        $customPayload = [
            [
                'id' => 9514,
                'variavel' => 'Teste',
                'resultados' => [
                    [
                        'series' => [
                            [
                                'localidade' => ['id' => '5200050'],
                                'serie' => ['2022' => '100.5'],
                            ],
                            [
                                'localidade' => ['id' => '5200100'],
                                'serie' => ['2022' => '-'],
                            ],
                            [
                                'localidade' => ['id' => '5200159'],
                                'serie' => ['2022' => '...'],
                            ],
                            [
                                'localidade' => ['id' => '5200209'],
                                'serie' => ['2022' => 'X'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/*' => Http::response($customPayload, 200),
        ]);

        $client = new SidraClient;
        $results = $client->fetchMunicipalityValues(9514, '2022', 93);

        $this->assertCount(2, $results);
        $this->assertSame(100.5, $results[0]['value']);
        $this->assertSame(0.0, $results[1]['value']);
    }

    public function test_fetches_and_aggregates_multiple_classification_categories_by_sum(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_9514_idade_0_14.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/9514/periodos/2022/variaveis/93*' => Http::response($fixture, 200),
        ]);

        $client = new SidraClient;
        $results = $client->fetchMunicipalityValues(
            table: 9514,
            period: '2022',
            variable: 93,
            classification: '287[93070,93084,93085]',
            aggregateSum: true
        );

        $this->assertCount(246, $results);

        // Abadia de Goiás (5200050): 0-4 (1590) + 5-9 (1619) + 10-14 (1601) = 4810
        $abadia = collect($results)->firstWhere('ibge_code', '5200050');
        $this->assertNotNull($abadia);
        $this->assertSame('2022', $abadia['period']);
        $this->assertSame(4810.0, $abadia['value']);
    }

    public function test_fetches_state_total_value_for_goias(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/sidra/sidra_4714_populacao_2022_estado.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v3/agregados/4714/periodos/2022/variaveis/93*' => Http::response($fixture, 200),
        ]);

        $client = new SidraClient;
        $stateValue = $client->fetchStateValue(table: 4714, period: '2022', variable: 93);

        $this->assertSame(7056495.0, $stateValue);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'localidades=N3%5B52%5D')
                || str_contains($request->url(), 'localidades=N3[52]');
        });
    }

    public function test_fetches_pib_per_capita_from_ibge_cidades_api(): void
    {
        $fixture = json_decode((string) file_get_contents(base_path('tests/Fixtures/ibge/ibge_cidades_pib_per_capita.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/api/v1/pesquisas/38/indicadores/47001/resultados/*' => Http::response($fixture, 200),
        ]);

        $client = new SidraClient;
        $results = $client->fetchPibPerCapitaValues(['5200050', '5200100']);

        $this->assertCount(2, $results);

        // Abadia de Goiás: 5200050 -> prefix 520005 -> value 24988.67 (2023)
        $abadia = collect($results)->firstWhere('ibge_code', '5200050');
        $this->assertNotNull($abadia);
        $this->assertSame('2023', $abadia['period']);
        $this->assertSame(24988.67, $abadia['value']);
    }
}
