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
}
