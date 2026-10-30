<?php

namespace App\Console\Commands;

use App\Models\Church;
use App\Models\Municipality;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class SyncMunicipalities extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'strategy:sync-municipalities
                            {--file= : Caminho para arquivo JSON local de municípios}
                            {--no-link : Não vincular igrejas aos municípios}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sincroniza os municípios de Goiás com a API do IBGE e vincula as igrejas cadastradas';

    public const IBGE_GO_MUNICIPIOS_URL = 'https://servicodados.ibge.gov.br/api/v1/localidades/estados/52/municipios';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Iniciando sincronização dos municípios de Goiás (IBGE)...');

        $data = $this->loadMunicipalityData();

        if (empty($data)) {
            $this->error('Não foi possível obter os dados dos municípios de Goiás.');

            return self::FAILURE;
        }

        $syncedCount = 0;

        foreach ($data as $item) {
            $ibgeCode = (string) ($item['id'] ?? '');
            $name = trim((string) ($item['nome'] ?? ''));

            if (blank($ibgeCode) || blank($name)) {
                continue;
            }

            $immediateRegion = $item['regiao-imediata'] ?? [];
            $intermediateRegion = $immediateRegion['regiao-intermediaria'] ?? [];
            $microRegion = $item['microrregiao'] ?? [];
            $mesoRegion = $microRegion['mesorregiao'] ?? [];

            Municipality::query()->updateOrCreate(
                ['ibge_code' => $ibgeCode],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'normalized_name' => Municipality::normalizeName($name),
                    'immediate_region_code' => (string) ($immediateRegion['id'] ?? ''),
                    'immediate_region_name' => (string) ($immediateRegion['nome'] ?? ''),
                    'intermediate_region_code' => (string) ($intermediateRegion['id'] ?? ''),
                    'intermediate_region_name' => (string) ($intermediateRegion['nome'] ?? ''),
                    'micro_region_name' => ! empty($microRegion['nome']) ? (string) $microRegion['nome'] : null,
                    'meso_region_name' => ! empty($mesoRegion['nome']) ? (string) $mesoRegion['nome'] : null,
                ]
            );

            $syncedCount++;
        }

        $this->info("✓ Sincronizados {$syncedCount} municípios de Goiás com sucesso.");

        if ($this->option('no-link')) {
            return self::SUCCESS;
        }

        $this->linkChurchesToMunicipalities();

        return self::SUCCESS;
    }

    /**
     * Carrega a coleção de municípios da API do IBGE ou do arquivo local.
     *
     * @return list<array<string, mixed>>
     */
    protected function loadMunicipalityData(): array
    {
        $filePath = $this->option('file');

        if (filled($filePath) && file_exists((string) $filePath)) {
            $this->line("Carregando municípios do arquivo local: {$filePath}");
            $content = (string) file_get_contents((string) $filePath);

            return (array) json_decode($content, true);
        }

        try {
            $this->line('Consultando API de Localidades do IBGE...');
            $response = Http::timeout(15)->get(self::IBGE_GO_MUNICIPIOS_URL);

            if ($response->successful()) {
                return (array) $response->json();
            }

            $this->warn("A API do IBGE retornou status HTTP {$response->status()}.");
        } catch (Throwable $e) {
            $this->warn("Falha de conexão com a API do IBGE: {$e->getMessage()}");
        }

        $fallbackPath = database_path('data/ibge_go_municipios.json');
        if (file_exists($fallbackPath)) {
            $this->line("Utilizando snapshot local de contingência: {$fallbackPath}");
            $content = (string) file_get_contents($fallbackPath);

            return (array) json_decode($content, true);
        }

        return [];
    }

    /**
     * Vincula as igrejas da convenção aos municípios pelo nome normalizado.
     */
    protected function linkChurchesToMunicipalities(): void
    {
        $this->line('Vinculando igrejas cadastradas aos municípios...');

        $churches = Church::query()->get();
        $linkedCount = 0;
        $unmatched = [];

        foreach ($churches as $church) {
            $municipality = Municipality::findByNormalizedName($church->city);

            if ($municipality) {
                $church->updateQuietly([
                    'municipality_id' => $municipality->id,
                    'city' => $municipality->name,
                ]);
                $linkedCount++;
            } else {
                $unmatched[] = [
                    'id' => $church->id,
                    'name' => $church->name,
                    'city' => $church->city ?? '(em branco)',
                ];
            }
        }

        $this->info("✓ {$linkedCount} igrejas vinculadas aos seus respectivos municípios.");

        if (! empty($unmatched)) {
            $count = count($unmatched);
            $this->warn("⚠️  {$count} igreja(s) não puderam ser vinculadas a nenhum município de Goiás:");
            $this->table(
                ['ID', 'Igreja', 'Cidade informada'],
                array_map(fn (array $item): array => [$item['id'], $item['name'], $item['city']], $unmatched)
            );
        }
    }
}
