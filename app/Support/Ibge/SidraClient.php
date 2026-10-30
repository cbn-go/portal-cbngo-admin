<?php

namespace App\Support\Ibge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SidraClient
{
    public const BASE_URL = 'https://servicodados.ibge.gov.br/api/v3/agregados';

    public const CIDADES_BASE_URL = 'https://servicodados.ibge.gov.br/api/v1/pesquisas';

    /**
     * Consulta dados agregados por município para Goiás (UF 52).
     *
     * @return list<array{ibge_code: string, period: string, value: float}>
     */
    public function fetchMunicipalityValues(
        int|string $table,
        string $period,
        int|string $variable,
        ?string $classification = null,
        string $localidades = 'N6[N3[52]]',
        bool $aggregateSum = false
    ): array {
        $url = sprintf('%s/%s/periodos/%s/variaveis/%s', self::BASE_URL, $table, $period, $variable);

        $queryParams = [
            'localidades' => $localidades,
        ];

        if (filled($classification)) {
            $queryParams['classificacao'] = $classification;
        }

        $retrySleep = app()->runningUnitTests() ? 0 : 1000;

        try {
            $response = Http::timeout(30)
                ->retry(3, $retrySleep, throw: false)
                ->withHeaders(['Accept-Encoding' => 'gzip'])
                ->withOptions(['verify' => config('services.ibge.verify_ssl', true)])
                ->get($url, $queryParams);

            if (! $response->successful()) {
                Log::warning("Erro ao consultar SIDRA (HTTP {$response->status()}): {$url}", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                return [];
            }

            /** @var mixed $data */
            $data = $response->json();

            if (! is_array($data)) {
                return [];
            }

            return $this->parseSeries($data, $aggregateSum);
        } catch (Throwable $e) {
            Log::error("Exceção ao consultar SIDRA: {$e->getMessage()}", [
                'url' => $url,
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Consulta o valor oficial publicado para o total da Unidade da Federação de Goiás (UF 52).
     */
    public function fetchStateValue(
        int|string $table,
        string $period,
        int|string $variable,
        ?string $classification = null,
        bool $aggregateSum = false,
        string $uf = '52'
    ): ?float {
        $url = sprintf('%s/%s/periodos/%s/variaveis/%s', self::BASE_URL, $table, $period, $variable);

        $queryParams = [
            'localidades' => "N3[{$uf}]",
        ];

        if (filled($classification)) {
            $queryParams['classificacao'] = $classification;
        }

        $retrySleep = app()->runningUnitTests() ? 0 : 1000;

        try {
            $response = Http::timeout(30)
                ->retry(3, $retrySleep, throw: false)
                ->withHeaders(['Accept-Encoding' => 'gzip'])
                ->withOptions(['verify' => config('services.ibge.verify_ssl', true)])
                ->get($url, $queryParams);

            if (! $response->successful()) {
                Log::warning("Erro ao consultar total estadual no SIDRA (HTTP {$response->status()}): {$url}", [
                    'status' => $response->status(),
                    'body' => substr($response->body(), 0, 500),
                ]);

                return null;
            }

            /** @var mixed $data */
            $data = $response->json();

            if (! is_array($data)) {
                return null;
            }

            $parsed = $this->parseSeries($data, $aggregateSum);
            if (empty($parsed)) {
                return null;
            }

            return $aggregateSum ? array_sum(array_column($parsed, 'value')) : $parsed[0]['value'];
        } catch (Throwable $e) {
            Log::error("Exceção ao consultar total estadual no SIDRA: {$e->getMessage()}", [
                'url' => $url,
                'exception' => $e,
            ]);

            return null;
        }
    }

    /**
     * Consulta o PIB per capita mais recente via API do IBGE Cidades (Pesquisa 38, Indicador 47001).
     *
     * @param  list<string>  $ibgeCodes  Códigos IBGE de 7 dígitos dos municípios
     * @return list<array{ibge_code: string, period: string, value: float}>
     */
    public function fetchPibPerCapitaValues(array $ibgeCodes): array
    {
        if (empty($ibgeCodes)) {
            return [];
        }

        $pipeCodes = implode('|', $ibgeCodes);
        $url = sprintf('%s/38/indicadores/47001/resultados/%s', self::CIDADES_BASE_URL, $pipeCodes);

        $retrySleep = app()->runningUnitTests() ? 0 : 1000;

        try {
            $response = Http::timeout(30)
                ->retry(3, $retrySleep, throw: false)
                ->withHeaders(['Accept-Encoding' => 'gzip'])
                ->withOptions(['verify' => config('services.ibge.verify_ssl', true)])
                ->get($url);

            if (! $response->successful()) {
                Log::warning("Erro ao consultar PIB per capita no IBGE Cidades (HTTP {$response->status()}): {$url}", [
                    'status' => $response->status(),
                ]);

                return [];
            }

            /** @var mixed $data */
            $data = $response->json();
            if (! is_array($data) || empty($data[0]['res'])) {
                return [];
            }

            /** @var list<array{localidade: string, res: array<string, string|float|null>}> $resultsList */
            $resultsList = $data[0]['res'];

            // Map 6-digit prefix to full 7-digit code
            $prefixMap = [];
            foreach ($ibgeCodes as $code) {
                $prefixMap[substr($code, 0, 6)] = $code;
            }

            $parsed = [];
            foreach ($resultsList as $item) {
                $locPrefix = $item['localidade'];
                $fullCode = $prefixMap[$locPrefix] ?? (strlen($locPrefix) === 7 ? $locPrefix : null);
                if ($fullCode === null) {
                    continue;
                }

                $resYears = $item['res'];
                if (empty($resYears)) {
                    continue;
                }

                // Filter valid non-empty values and find the latest year
                $validYears = array_filter(
                    $resYears,
                    fn ($val) => $val !== null && $val !== '' && is_numeric($val)
                );

                if (empty($validYears)) {
                    continue;
                }

                $latestYear = (string) array_key_last($validYears);
                $numericValue = (float) $validYears[$latestYear];

                $parsed[] = [
                    'ibge_code' => $fullCode,
                    'period' => $latestYear,
                    'value' => $numericValue,
                ];
            }

            return $parsed;
        } catch (Throwable $e) {
            Log::error("Exceção ao consultar PIB per capita no IBGE Cidades: {$e->getMessage()}", [
                'url' => $url,
                'exception' => $e,
            ]);

            return [];
        }
    }

    /**
     * Parseia os resultados da resposta do SIDRA extraindo código IBGE, período e valor numérico.
     *
     * @param  list<array<string, mixed>>  $data
     * @return list<array{ibge_code: string, period: string, value: float}>
     */
    protected function parseSeries(array $data, bool $aggregateSum = false): array
    {
        $parsed = [];

        foreach ($data as $item) {
            $resultados = $item['resultados'] ?? [];
            if (! is_array($resultados)) {
                continue;
            }

            foreach ($resultados as $resultado) {
                $series = $resultado['series'] ?? [];
                if (! is_array($series)) {
                    continue;
                }

                foreach ($series as $serieItem) {
                    $ibgeCode = (string) ($serieItem['localidade']['id'] ?? '');
                    if (blank($ibgeCode)) {
                        continue;
                    }

                    $serieMap = $serieItem['serie'] ?? [];
                    if (! is_array($serieMap) || empty($serieMap)) {
                        continue;
                    }

                    $periodKey = (string) array_key_first($serieMap);
                    $rawValue = $serieMap[$periodKey] ?? null;

                    if ($rawValue === null || $rawValue === '...' || $rawValue === 'X') {
                        continue;
                    }

                    if ($rawValue === '-') {
                        $numericValue = 0.0;
                    } elseif (is_numeric($rawValue)) {
                        $numericValue = (float) $rawValue;
                    } else {
                        continue;
                    }

                    $parsed[] = [
                        'ibge_code' => $ibgeCode,
                        'period' => $periodKey,
                        'value' => $numericValue,
                    ];
                }
            }
        }

        if ($aggregateSum && ! empty($parsed)) {
            /** @var array<string, array{ibge_code: string, period: string, value: float}> $aggregated */
            $aggregated = [];
            foreach ($parsed as $row) {
                $code = $row['ibge_code'];
                if (! isset($aggregated[$code])) {
                    $aggregated[$code] = $row;
                } else {
                    $aggregated[$code]['value'] += $row['value'];
                }
            }

            return array_values($aggregated);
        }

        return $parsed;
    }
}
