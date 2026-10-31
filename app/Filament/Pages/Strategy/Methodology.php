<?php

namespace App\Filament\Pages\Strategy;

use App\Enums\StrategyIndicator;
use App\Filament\Concerns\HasStrategySourceFooter;
use App\Services\Strategy\StateIndicatorService;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/**
 * Painel de Estratégia: fonte, tabela, ano e limitações de cada indicador.
 */
class Methodology extends Page
{
    use HasStrategySourceFooter;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    protected static ?string $navigationGroup = 'Estratégia';

    protected static ?string $navigationLabel = 'Metodologia e fontes';

    protected static ?string $title = 'Metodologia e fontes';

    protected static ?string $slug = 'estrategia/metodologia';

    protected static ?int $navigationSort = 3;

    protected static string $view = 'filament.pages.strategy.methodology';

    /**
     * Limitações exibidas ao fim da página.
     *
     * @var list<string>
     */
    public const LIMITATIONS = [
        'O Censo 2022 só divulga religião por município em grandes grupos ("Evangélicas"). O número de batistas por município vem do Censo 2010.',
        'No Censo 2022 a religião foi investigada apenas para pessoas de 10 anos ou mais; no Censo 2010, para toda a população. Por isso a variação 2010 → 2022 compara bases diferentes.',
        'O PIB per capita vem do IBGE Cidades (PIB dos Municípios), que é publicado com cerca de dois anos de defasagem.',
        'Os valores de Goiás são recompostos a partir dos 246 municípios (somas e razões entre somas), não lidos diretamente do IBGE.',
        'Os dados são sincronizados uma vez por mês pelo comando agendado. O painel não consulta o IBGE durante a navegação.',
        'A cobertura considera só as igrejas ativas vinculadas a um município no cadastro.',
    ];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active && $user->role->hasAdminPanelAccess();
    }

    public function getSubheading(): string
    {
        return 'De onde vem cada número do Painel de Estratégia, de que ano é e quais são os seus limites.';
    }

    /**
     * @return array<string, list<array{indicator: StrategyIndicator, table: string, periods: list<string>, lastSyncedAt: ?Carbon}>>
     */
    public function indicatorGroups(): array
    {
        $service = app(StateIndicatorService::class);
        $groups = [];

        foreach (StrategyIndicator::cases() as $indicator) {
            $config = $indicator->sidraConfig();

            $groups[$indicator->group()][] = [
                'indicator' => $indicator,
                'table' => match (true) {
                    $config !== null => "SIDRA {$config['table']}",
                    $indicator === StrategyIndicator::PIB_PER_CAPITA => 'IBGE Cidades (pesquisa 38)',
                    default => 'Calculado',
                },
                'periods' => $service->periodsFor($indicator),
                'lastSyncedAt' => $service->lastSyncedAt($indicator),
            ];
        }

        return $groups;
    }

    /**
     * @return list<array{label: string, description: string, source: string, weight: float}>
     */
    public function priorityFactors(): array
    {
        /** @var array<string, float> $weights */
        $weights = config('strategy.priority_index.weights', []);
        /** @var array<string, array{label: string, description: string, source: string}> $metadata */
        $metadata = config('strategy.priority_index.metadata', []);

        return collect($weights)->map(fn (float $weight, string $key): array => [
            'label' => $metadata[$key]['label'] ?? $key,
            'description' => $metadata[$key]['description'] ?? '',
            'source' => $metadata[$key]['source'] ?? '',
            'weight' => $weight,
        ])->values()->all();
    }
}
