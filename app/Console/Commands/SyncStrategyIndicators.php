<?php

namespace App\Console\Commands;

use App\Enums\StrategyIndicator;
use App\Models\Municipality;
use App\Models\MunicipalityIndicator;
use App\Support\Ibge\SidraClient;
use Illuminate\Console\Command;

class SyncStrategyIndicators extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'strategy:sync-indicators
                            {--indicator= : Sincronizar apenas um indicador específico}
                            {--dry-run : Executar a coleta sem persistir no banco}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza indicadores territoriais e demográficos de Goiás da API do SIDRA/IBGE';

    /**
     * Execute the console command.
     */
    public function handle(SidraClient $client): int
    {
        $this->info('Iniciando sincronização de indicadores do Painel de Estratégia...');

        $isDryRun = (bool) $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('Modo DRY-RUN ativado: nenhum dado será gravado no banco.');
        }

        $indicators = $this->determineIndicatorsToSync();
        if ($indicators === null) {
            return self::FAILURE;
        }

        if (empty($indicators)) {
            $this->warn('Nenhum indicador configurado para sincronização.');

            return self::SUCCESS;
        }

        /** @var array<string, int> $municipalitiesMap */
        $municipalitiesMap = Municipality::query()->pluck('id', 'ibge_code')->all();

        if (empty($municipalitiesMap)) {
            $this->error('A base de municípios de Goiás está vazia. Execute primeiro o comando "strategy:sync-municipalities".');

            return self::FAILURE;
        }

        $summary = [];
        $syncedIndicators = [];

        foreach ($indicators as $indicator) {
            if ($indicator === StrategyIndicator::PIB_PER_CAPITA) {
                $this->line("Consultando IBGE Cidades para [{$indicator->label()}]...");
                $results = $client->fetchPibPerCapitaValues(array_keys($municipalitiesMap));
                $configPeriod = 'Mais recente';
            } else {
                $config = $indicator->sidraConfig();
                if ($config === null) {
                    continue;
                }

                $this->line("Consultando SIDRA para [{$indicator->label()}]...");
                $results = $client->fetchMunicipalityValues(
                    $config['table'],
                    $config['period'],
                    $config['variable'],
                    $config['classification'],
                    aggregateSum: $config['aggregate_sum'] ?? false
                );
                $configPeriod = $config['period'];
            }

            if (empty($results)) {
                $this->warn("⚠️  Não foi possível obter dados para [{$indicator->label()}]. Mantendo dados existentes.");
                $summary[] = [
                    'indicator' => $indicator->label(),
                    'period' => $configPeriod,
                    'synced' => 0,
                    'status' => 'Falha (mantido anterior)',
                ];

                continue;
            }

            $syncedCount = 0;

            if (! $isDryRun) {
                $fetchedAt = now();
                $source = $indicator->source();

                foreach ($results as $row) {
                    $municipalityId = $municipalitiesMap[$row['ibge_code']] ?? null;
                    if ($municipalityId === null) {
                        continue;
                    }

                    MunicipalityIndicator::query()->updateOrCreate(
                        [
                            'municipality_id' => $municipalityId,
                            'indicator' => $indicator,
                            'period' => $row['period'],
                        ],
                        [
                            'value' => $row['value'],
                            'source' => $source,
                            'fetched_at' => $fetchedAt,
                        ]
                    );

                    $syncedCount++;
                }
            } else {
                $syncedCount = count($results);
            }

            $actualPeriod = $results[0]['period'];
            $statusLabel = $isDryRun ? 'Simulado (Dry-Run)' : 'Sucesso';

            $this->info("✓ [{$indicator->label()}]: {$syncedCount} municípios processados (Período {$actualPeriod}).");

            $summary[] = [
                'indicator' => $indicator->label(),
                'period' => $actualPeriod,
                'synced' => $syncedCount,
                'status' => $statusLabel,
            ];

            $syncedIndicators[] = $indicator;
        }

        // Executa o motor de cálculo para indicadores derivados (Crescimento % e Densidade Demográfica)
        $this->calculateDerivedIndicators($municipalitiesMap, $isDryRun, $summary);

        $this->newLine();
        $this->table(
            ['Indicador', 'Período', 'Municípios', 'Status'],
            $summary
        );

        // Executa conferência dos totais do estado contra os valores oficiais publicados no IBGE
        $this->crossCheckStateTotals($client, $syncedIndicators);

        return self::SUCCESS;
    }

    /**
     * Computa indicadores derivados automaticamente ao término da sincronização.
     *
     * @param  array<string, int>  $municipalitiesMap
     * @param  list<array{indicator: string, period: string, synced: int, status: string}>  $summary
     */
    protected function calculateDerivedIndicators(array $municipalitiesMap, bool $isDryRun, array &$summary): void
    {
        $this->line('Calculando indicadores derivados (Crescimento % e Densidade Demográfica)...');

        $censusPop = MunicipalityIndicator::query()
            ->where('indicator', StrategyIndicator::POPULATION_CENSUS_2022)
            ->pluck('value', 'municipality_id');

        $estPop = MunicipalityIndicator::query()
            ->where('indicator', StrategyIndicator::POPULATION_ESTIMATE)
            ->get(['municipality_id', 'value', 'period'])
            ->keyBy('municipality_id');

        $areaTerritorial = MunicipalityIndicator::query()
            ->where('indicator', StrategyIndicator::TERRITORIAL_AREA)
            ->pluck('value', 'municipality_id');

        $growthCount = 0;
        $densityCount = 0;
        $latestEstPeriod = '2026';
        $fetchedAt = now();

        foreach ($municipalitiesMap as $muniId) {
            $pop2022 = $censusPop->get($muniId);
            $estRow = $estPop->get($muniId);
            $area = $areaTerritorial->get($muniId);

            // 1. Crescimento Populacional (%) = ((Pop Estimada - Pop Censo 2022) / Pop Censo 2022) * 100
            if ($pop2022 !== null && (float) $pop2022 > 0 && $estRow !== null) {
                $latestEstPeriod = (string) $estRow->period;
                $growthValue = round((((float) $estRow->value - (float) $pop2022) / (float) $pop2022) * 100, 2);

                if (! $isDryRun) {
                    MunicipalityIndicator::query()->updateOrCreate(
                        [
                            'municipality_id' => $muniId,
                            'indicator' => StrategyIndicator::POPULATION_GROWTH,
                            'period' => $latestEstPeriod,
                        ],
                        [
                            'value' => $growthValue,
                            'source' => StrategyIndicator::POPULATION_GROWTH->source(),
                            'fetched_at' => $fetchedAt,
                        ]
                    );
                }
                $growthCount++;
            }

            // 2. Densidade Demográfica = Pop Censo 2022 / Área Territorial
            if ($pop2022 !== null && $area !== null && (float) $area > 0) {
                $densityValue = round((float) $pop2022 / (float) $area, 2);

                if (! $isDryRun) {
                    MunicipalityIndicator::query()->updateOrCreate(
                        [
                            'municipality_id' => $muniId,
                            'indicator' => StrategyIndicator::DEMOGRAPHIC_DENSITY,
                            'period' => '2022',
                        ],
                        [
                            'value' => $densityValue,
                            'source' => StrategyIndicator::DEMOGRAPHIC_DENSITY->source(),
                            'fetched_at' => $fetchedAt,
                        ]
                    );
                }
                $densityCount++;
            }
        }

        $status = $isDryRun ? 'Simulado (Dry-Run)' : 'Calculado';

        if ($growthCount > 0) {
            $summary[] = [
                'indicator' => StrategyIndicator::POPULATION_GROWTH->label(),
                'period' => $latestEstPeriod,
                'synced' => $growthCount,
                'status' => $status,
            ];
            $this->info('✓ ['.StrategyIndicator::POPULATION_GROWTH->label()."]: {$growthCount} municípios calculados.");
        }

        if ($densityCount > 0) {
            $summary[] = [
                'indicator' => StrategyIndicator::DEMOGRAPHIC_DENSITY->label(),
                'period' => '2022',
                'synced' => $densityCount,
                'status' => $status,
            ];
            $this->info('✓ ['.StrategyIndicator::DEMOGRAPHIC_DENSITY->label()."]: {$densityCount} municípios calculados.");
        }
    }

    /**
     * Cruza os totais dos municípios com os valores oficiais publicados para o Estado de Goiás (UF 52).
     *
     * @param  list<StrategyIndicator>  $syncedIndicators
     */
    protected function crossCheckStateTotals(SidraClient $client, array $syncedIndicators): void
    {
        $rows = [];

        foreach ($syncedIndicators as $indicator) {
            $config = $indicator->sidraConfig();
            if ($config === null) {
                continue;
            }

            $statePublished = $client->fetchStateValue(
                $config['table'],
                $config['period'],
                $config['variable'],
                $config['classification'],
                $config['aggregate_sum'] ?? false
            );

            if ($statePublished === null) {
                continue;
            }

            $municipalSum = (float) MunicipalityIndicator::query()
                ->where('indicator', $indicator)
                ->sum('value');

            $diff = $municipalSum - $statePublished;
            $pctDiff = $statePublished > 0 ? abs($diff / $statePublished) * 100 : 0.0;

            $status = match (true) {
                abs($diff) < 0.0001 => '✓ Exato',
                $pctDiff <= 0.05 => '✓ Tolerância Arredondamento',
                default => '⚠️ Divergente',
            };

            $rows[] = [
                'indicator' => $indicator->label(),
                'municipal_sum' => number_format($municipalSum, 2, ',', '.'),
                'state_published' => number_format($statePublished, 2, ',', '.'),
                'difference' => ($diff > 0 ? '+' : '').number_format($diff, 2, ',', '.'),
                'status' => $status,
            ];
        }

        if (! empty($rows)) {
            $this->newLine();
            $this->info('Conferência de Totais de Goiás (IBGE N3[52]):');
            $this->table(
                ['Indicador', 'Soma 246 Mun.', 'Total GO Publicado', 'Diferença', 'Status'],
                $rows
            );
        }
    }

    /**
     * @return list<StrategyIndicator>|null
     */
    protected function determineIndicatorsToSync(): ?array
    {
        $indicatorOption = $this->option('indicator');

        if (filled($indicatorOption)) {
            $indicator = StrategyIndicator::tryFrom((string) $indicatorOption);

            if ($indicator === null) {
                $valid = implode(', ', array_map(fn (StrategyIndicator $i) => $i->value, StrategyIndicator::cases()));
                $this->error("Indicador inválido: '{$indicatorOption}'. Opções válidas: {$valid}");

                return null;
            }

            if ($indicator->isCalculated()) {
                $this->error("O indicador '{$indicator->label()}' é derivado e não pode ser consultado diretamente no SIDRA.");

                return null;
            }

            return [$indicator];
        }

        return [
            StrategyIndicator::POPULATION_CENSUS_2022,
            StrategyIndicator::POPULATION_ESTIMATE,
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
            StrategyIndicator::PIB_PER_CAPITA,
        ];
    }
}
