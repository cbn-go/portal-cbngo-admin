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
            $this->warn('Nenhum indicador configurado para sincronização com o SIDRA.');

            return self::SUCCESS;
        }

        /** @var array<string, int> $municipalitiesMap */
        $municipalitiesMap = Municipality::query()->pluck('id', 'ibge_code')->all();

        if (empty($municipalitiesMap)) {
            $this->error('A base de municípios de Goiás está vazia. Execute primeiro o comando "strategy:sync-municipalities".');

            return self::FAILURE;
        }

        $summary = [];

        foreach ($indicators as $indicator) {
            $config = $indicator->sidraConfig();
            if ($config === null) {
                continue;
            }

            $this->line("Consultando SIDRA para [{$indicator->label()}]...");

            $results = $client->fetchMunicipalityValues(
                $config['table'],
                $config['period'],
                $config['variable'],
                $config['classification']
            );

            if (empty($results)) {
                $this->warn("⚠️  Não foi possível obter dados para [{$indicator->label()}]. Mantendo dados existentes.");
                $summary[] = [
                    'indicator' => $indicator->label(),
                    'period' => $config['period'],
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
        }

        $this->newLine();
        $this->table(
            ['Indicador', 'Período', 'Municípios', 'Status'],
            $summary
        );

        return self::SUCCESS;
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

        return array_values(array_filter(
            StrategyIndicator::cases(),
            fn (StrategyIndicator $i): bool => ! $i->isCalculated()
        ));
    }
}
