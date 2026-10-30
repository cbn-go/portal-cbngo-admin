<?php

namespace App\Support\Ibge;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SidraClient
{
    public const BASE_URL = 'https://servicodados.ibge.gov.br/api/v3/agregados';

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
        string $localidades = 'N6[N3[52]]'
    ): array {
        $url = sprintf('%s/%s/periodos/%s/variaveis/%s', self::BASE_URL, $table, $period, $variable);

        $queryParams = [
            'localidades' => $localidades,
        ];

        if (filled($classification)) {
            $queryParams['classificacao'] = $classification;
        }

        try {
            $response = Http::timeout(30)
                ->retry(3, 1000, throw: false)
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

            return $this->parseSeries($data);
        } catch (Throwable $e) {
            Log::error("Exceção ao consultar SIDRA: {$e->getMessage()}", [
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
    protected function parseSeries(array $data): array
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

        return $parsed;
    }
}
