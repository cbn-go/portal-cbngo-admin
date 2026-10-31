<?php

namespace App\Console\Commands;

use App\Models\Municipality;
use App\Services\Strategy\MunicipalityMap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncMunicipalityMap extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'strategy:sync-map
                            {--if-missing : Baixar apenas se a malha ainda não existir}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Baixa a malha municipal de Goiás da API de Malhas do IBGE para o mapa do Painel de Estratégia';

    public const IBGE_MALHA_URL = 'https://servicodados.ibge.gov.br/api/v3/malhas/estados/52';

    /**
     * Abaixo disso a resposta está incompleta (Goiás tem 246 municípios).
     */
    public const MIN_FEATURES = 240;

    public function handle(MunicipalityMap $map): int
    {
        if ($this->option('if-missing') && $map->exists()) {
            $this->info('Malha municipal já existe. Nada a fazer.');

            return self::SUCCESS;
        }

        $this->line('Consultando a API de Malhas do IBGE...');

        $body = $this->download();

        if ($body === null) {
            $this->error('Não foi possível baixar a malha municipal. Mantendo o arquivo anterior.');

            return self::FAILURE;
        }

        $error = $this->validate($body);

        if ($error !== null) {
            $this->error("Malha municipal inválida: {$error}. Mantendo o arquivo anterior.");

            return self::FAILURE;
        }

        // Grava em arquivo temporário e renomeia: a tela nunca lê um arquivo pela metade.
        File::ensureDirectoryExists(dirname($map->path()));
        $temporary = $map->path().'.tmp';
        File::put($temporary, $body);
        File::move($temporary, $map->path());

        $this->info('✓ Malha municipal salva em '.$map->path().' ('.number_format(strlen($body) / 1024, 0, ',', '.').' KB).');

        return self::SUCCESS;
    }

    private function download(): ?string
    {
        $retrySleep = app()->runningUnitTests() ? 0 : 1000;

        try {
            $response = Http::timeout(30)
                ->retry(3, $retrySleep, throw: false)
                ->withOptions(['verify' => config('services.ibge.verify_ssl', true)])
                ->get(self::IBGE_MALHA_URL, [
                    'intrarregiao' => 'municipio',
                    'formato' => 'application/vnd.geo+json',
                    'qualidade' => 'minima',
                ]);
        } catch (Throwable $e) {
            Log::error('Exceção ao baixar a malha municipal do IBGE: '.$e->getMessage());

            return null;
        }

        if (! $response->successful()) {
            Log::error('Falha ao baixar a malha municipal do IBGE', ['status' => $response->status()]);

            return null;
        }

        return $response->body();
    }

    /**
     * Confere se a resposta é a malha de Goiás inteira e se os códigos batem com a base.
     */
    private function validate(string $body): ?string
    {
        $data = json_decode($body, true);

        if (! is_array($data) || ($data['type'] ?? null) !== 'FeatureCollection' || ! is_array($data['features'] ?? null)) {
            return 'a resposta não é um FeatureCollection';
        }

        $features = $data['features'];

        if (count($features) < self::MIN_FEATURES) {
            return 'veio com '.count($features).' municípios, esperado pelo menos '.self::MIN_FEATURES;
        }

        $known = Municipality::query()->pluck('ibge_code')->flip();

        foreach ($features as $feature) {
            $code = (string) ($feature['properties']['codarea'] ?? '');

            if ($code === '' || ! isset($feature['geometry']['type'])) {
                return 'há município sem código ou sem geometria';
            }

            if ($known->isNotEmpty() && ! $known->has($code)) {
                return "o código {$code} não está na base de municípios";
            }
        }

        return null;
    }
}
