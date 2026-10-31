<?php

namespace App\Services\Strategy;

use App\DTOs\Strategy\MunicipalityCoverageData;
use App\Enums\StrategyIndicator;
use App\Filament\Resources\ChurchResource;
use App\Filament\Resources\MunicipalityResource;
use App\Models\Church;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Dados do mapa coroplético do Painel de Estratégia: a malha baixada do IBGE,
 * os valores por município e as faixas de cada camada.
 */
class MunicipalityMap
{
    /**
     * Rampa sequencial azul (5 classes, do menor para o maior), validada como
     * ordinal: um só tom, luminosidade monotônica e o passo mais claro visível
     * sobre o fundo claro.
     *
     * @var list<string>
     */
    public const RAMP = ['#86b6ef', '#5598e7', '#2a78d6', '#1c5cab', '#0d366b'];

    /**
     * Município sem igreja ativa: laranja, separado de toda a rampa azul
     * (também para daltônicos) e sempre nomeado na legenda e no tooltip.
     */
    public const NO_CHURCH_COLOR = '#eb6834';

    public const NO_DATA_COLOR = '#d9d8d4';

    public function __construct(
        private ChurchCoverageService $coverage,
        private StateIndicatorService $indicators,
    ) {}

    public function path(): string
    {
        return (string) config('strategy.map_path', storage_path('app/strategy/goias-municipios.geojson'));
    }

    public function exists(): bool
    {
        return is_file($this->path());
    }

    public function lastSyncedAt(): ?Carbon
    {
        return $this->exists() ? Carbon::createFromTimestamp((int) filemtime($this->path())) : null;
    }

    /**
     * Valores de cada município, indexados pelo código IBGE (`codarea` da malha).
     *
     * Códigos numéricos viram chaves inteiras no PHP; no JSON do mapa voltam a ser texto.
     *
     * @return array<int|string, array{id: int, name: string, url: string, churches: int, population: ?int, evangelical_pct: ?float, inhabitants_per_church: ?float, priority: ?float}>
     */
    public function municipalities(): array
    {
        return $this->coverage->getAllMunicipalitiesCoverage()
            ->mapWithKeys(function (MunicipalityCoverageData $c): array {
                $evangelical = $this->indicators->valueFor($c->municipalityId, StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022);

                return [$c->ibgeCode => [
                    'id' => $c->municipalityId,
                    'name' => $c->name,
                    'url' => MunicipalityResource::getUrl('view', ['record' => $c->municipalityId]),
                    'churches' => $c->activeChurchesCount,
                    'population' => $c->population,
                    'evangelical_pct' => $evangelical !== null ? (float) $evangelical->value : null,
                    'inhabitants_per_church' => $c->inhabitantsPerChurch,
                    'priority' => $c->priorityIndex,
                ]];
            })
            ->all();
    }

    /**
     * Igrejas ativas com coordenadas, para os marcadores.
     *
     * @return list<array{name: string, city: ?string, lat: float, lng: float, url: string}>
     */
    public function churches(): array
    {
        return Church::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->orderBy('name')
            ->get(['id', 'name', 'city', 'latitude', 'longitude'])
            ->map(fn (Church $church): array => [
                'name' => $church->name,
                'city' => $church->city,
                'lat' => (float) $church->latitude,
                'lng' => (float) $church->longitude,
                'url' => ChurchResource::getUrl('edit', ['record' => $church]),
            ])
            ->values()
            ->all();
    }

    /**
     * Igrejas ativas sem coordenadas: entram só pela contagem do município.
     */
    public function churchesWithoutCoordinates(): int
    {
        return Church::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->count();
    }

    /**
     * Camadas do seletor, com faixas e cores prontas para a legenda.
     *
     * @param  array<int|string, array<string, mixed>>  $municipalities
     * @return array<string, array{label: string, unit: string, description: string, classes: list<array{min: ?float, max: ?float, color: string, label: string}>, special: list<array{key: string, color: string, label: string}>}>
     */
    public function layers(array $municipalities): array
    {
        $values = fn (string $key): Collection => collect($municipalities)->pluck($key)->filter(fn ($v): bool => $v !== null)->map(fn ($v): float => (float) $v);
        $inhabitants = fn (float $v): string => StrategyIndicator::POPULATION_ESTIMATE->format($v);

        return [
            'inhabitants_per_church' => [
                'label' => 'Habitantes por igreja',
                'unit' => 'habitantes',
                'description' => 'Quanto mais escuro, mais gente para cada igreja ativa da CBN-GO.',
                'classes' => $this->quantileClasses($values('inhabitants_per_church'), $inhabitants),
                'special' => [
                    ['key' => 'no_church', 'color' => self::NO_CHURCH_COLOR, 'label' => 'Sem igreja da CBN-GO'],
                    ['key' => 'no_data', 'color' => self::NO_DATA_COLOR, 'label' => 'Sem dados do IBGE'],
                ],
            ],
            // Quantis e não as faixas alta/média/baixa: com os pesos atuais quase
            // todos os municípios caem em "média" e o mapa ficaria de uma cor só.
            'priority' => [
                'label' => 'Índice de prioridade',
                'unit' => 'pontos',
                'description' => 'Combina população, crescimento, não evangélicos e ausência de igreja (0 a 100). Quanto mais escuro, maior a prioridade.',
                'classes' => $this->quantileClasses($values('priority'), fn (float $v): string => number_format($v, 1, ',', '.')),
                'special' => [
                    ['key' => 'no_data', 'color' => self::NO_DATA_COLOR, 'label' => 'Sem dados do IBGE'],
                ],
            ],
            'population' => [
                'label' => 'População',
                'unit' => 'habitantes',
                'description' => 'População estimada mais recente (ou do Censo 2022).',
                'classes' => $this->quantileClasses($values('population'), $inhabitants),
                'special' => [
                    ['key' => 'no_data', 'color' => self::NO_DATA_COLOR, 'label' => 'Sem dados do IBGE'],
                ],
            ],
            'evangelical_pct' => [
                'label' => '% evangélicos (Censo 2022)',
                'unit' => '%',
                'description' => 'Pessoas de 10 anos ou mais que se declaram evangélicas.',
                'classes' => $this->quantileClasses($values('evangelical_pct'), fn (float $v): string => StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022->format($v)),
                'special' => [
                    ['key' => 'no_data', 'color' => self::NO_DATA_COLOR, 'label' => 'Sem dados do IBGE'],
                ],
            ],
        ];
    }

    /**
     * Cinco classes por quantis. Com poucos valores distintos, junta as classes
     * repetidas em vez de criar faixas vazias.
     *
     * @param  Collection<int, float>  $values
     * @param  callable(float): string  $format
     * @return list<array{min: ?float, max: ?float, color: string, label: string}>
     */
    public function quantileClasses(Collection $values, callable $format): array
    {
        $sorted = $values->sort()->values();

        if ($sorted->isEmpty()) {
            return [];
        }

        $count = count(self::RAMP);
        $lowest = (float) $sorted->first();

        /** @var list<float> $breaks */
        $breaks = [];

        foreach (range(1, $count - 1) as $i) {
            $break = (float) $sorted[(int) floor($i * ($sorted->count() - 1) / $count)];

            if ($break > $lowest && ! in_array($break, $breaks, true)) {
                $breaks[] = $break;
            }
        }

        $classCount = count($breaks) + 1;
        $classes = [];

        for ($i = 0; $i < $classCount; $i++) {
            $min = $breaks[$i - 1] ?? null;
            $max = $breaks[$i] ?? null;
            // Distribui as cores pela rampa inteira mesmo quando sobram menos classes.
            $color = self::RAMP[$classCount === 1 ? $count - 1 : (int) round($i * ($count - 1) / ($classCount - 1))];

            $classes[] = [
                'min' => $min,
                'max' => $max,
                'color' => $color,
                'label' => match (true) {
                    $min !== null && $max !== null => $format($min).' a '.$format($max),
                    $max !== null => 'Menos de '.$format($max),
                    $min !== null => $format($min).' ou mais',
                    default => 'Todos os municípios',
                },
            ];
        }

        return $classes;
    }
}
