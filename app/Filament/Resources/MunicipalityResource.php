<?php

namespace App\Filament\Resources;

use App\DTOs\Strategy\MunicipalityCoverageData;
use App\Enums\PopulationBand;
use App\Enums\PriorityBand;
use App\Enums\StrategyIndicator;
use App\Filament\Resources\MunicipalityResource\Pages;
use App\Models\Municipality;
use App\Services\Strategy\ChurchCoverageService;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Painel de Estratégia: os 246 municípios de Goiás com indicadores do IBGE e
 * a cobertura da CBN-GO. Somente leitura; os dados vêm da sincronização.
 */
class MunicipalityResource extends Resource
{
    protected static ?string $model = Municipality::class;

    protected static ?string $navigationIcon = 'heroicon-o-map-pin';

    protected static ?string $navigationGroup = 'Estratégia';

    protected static ?string $navigationLabel = 'Municípios';

    protected static ?string $modelLabel = 'Município';

    protected static ?string $pluralModelLabel = 'Municípios';

    protected static ?string $slug = 'estrategia/municipios';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Indicadores lidos direto do banco como colunas da listagem.
     *
     * @var array<string, StrategyIndicator>
     */
    public const INDICATOR_COLUMNS = [
        'growth' => StrategyIndicator::POPULATION_GROWTH,
        'evangelical_pct' => StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022,
        'baptist_pct_2010' => StrategyIndicator::RELIGION_BAPTIST_PCT_2010,
        'pib_per_capita' => StrategyIndicator::PIB_PER_CAPITA,
    ];

    /**
     * @return Builder<Municipality>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<Municipality> $query */
        $query = parent::getEloquentQuery()
            ->withCount(['churches as active_churches_count' => fn (Builder $q): Builder => $q->where('is_active', true)]);

        foreach (self::INDICATOR_COLUMNS as $alias => $indicator) {
            $query->withIndicatorValue($indicator, $alias);
        }

        return $query;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Município')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('immediate_region_name')
                    ->label('Região imediata')
                    ->sortable(),

                self::coverageColumn('population', 'População')
                    ->formatStateUsing(fn (?int $state): string => StrategyIndicator::POPULATION_ESTIMATE->format($state)),

                Tables\Columns\TextColumn::make('active_churches_count')
                    ->label('Igrejas')
                    ->sortable()
                    ->alignEnd(),

                self::coverageColumn('inhabitantsPerChurch', 'Hab. por igreja')
                    ->formatStateUsing(fn (?float $state): string => StrategyIndicator::POPULATION_ESTIMATE->format($state)),

                self::coverageColumn('priorityIndex', 'Prioridade')
                    ->badge()
                    ->color(fn (?float $state): ?string => PriorityBand::forScore($state)?->getColor())
                    ->formatStateUsing(fn (?float $state): string => number_format((float) $state, 1, ',', '.')),

                self::coverageColumn('nonEvangelicalPopulation', 'Não evangélicos')
                    ->formatStateUsing(fn (?int $state): string => StrategyIndicator::NON_EVANGELICAL_POPULATION_ESTIMATE->format($state))
                    ->toggleable(isToggledHiddenByDefault: true),

                self::coverageColumn('publishedSocialProjectsCount', 'Projetos sociais')
                    ->toggleable(isToggledHiddenByDefault: true),

                ...collect(self::INDICATOR_COLUMNS)->map(fn (StrategyIndicator $indicator, string $alias): Tables\Columns\TextColumn => Tables\Columns\TextColumn::make($alias)
                    ->label(self::shortLabel($indicator))
                    ->formatStateUsing(fn ($state): string => $indicator->format($state))
                    ->placeholder('—')
                    ->sortable()
                    ->alignEnd()
                    ->toggleable(isToggledHiddenByDefault: $indicator === StrategyIndicator::PIB_PER_CAPITA))->values()->all(),
            ])
            ->defaultSort(fn (Builder $query): Builder => self::orderByCoverage($query, 'priorityIndex', 'desc'))
            ->filters([
                Tables\Filters\SelectFilter::make('immediate_region_code')
                    ->label('Região imediata')
                    ->options(fn (): array => Municipality::query()
                        ->orderBy('immediate_region_name')
                        ->pluck('immediate_region_name', 'immediate_region_code')
                        ->all()),

                Tables\Filters\TernaryFilter::make('has_church')
                    ->label('Igreja da CBN-GO')
                    ->trueLabel('Com igreja')
                    ->falseLabel('Sem igreja')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('churches', fn (Builder $q): Builder => $q->where('is_active', true)),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('churches', fn (Builder $q): Builder => $q->where('is_active', true)),
                    ),

                Tables\Filters\SelectFilter::make('population_band')
                    ->label('População')
                    ->options(PopulationBand::class)
                    ->multiple()
                    ->query(function (Builder $query, array $data): Builder {
                        $bands = array_map(fn (string $value): PopulationBand => PopulationBand::from($value), $data['values'] ?? []);

                        return $bands === []
                            ? $query
                            : $query->whereIn('municipalities.id', self::coverage()
                                ->filter(fn (MunicipalityCoverageData $c): bool => collect($bands)->contains(fn (PopulationBand $band): bool => $band->contains($c->population)))
                                ->keys()
                                ->all());
                    }),

                Tables\Filters\SelectFilter::make('priority_band')
                    ->label('Índice de prioridade')
                    ->options(PriorityBand::class)
                    ->query(fn (Builder $query, array $data): Builder => blank($data['value'])
                        ? $query
                        : $query->whereIn('municipalities.id', self::coverage()
                            ->filter(fn (MunicipalityCoverageData $c): bool => PriorityBand::forScore($c->priorityIndex)?->value === $data['value'])
                            ->keys()
                            ->all())),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Detalhes'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMunicipalities::route('/'),
            'view' => Pages\ViewMunicipality::route('/{record}'),
        ];
    }

    /**
     * Cobertura e índice de prioridade de todos os municípios, por id.
     * Calculado uma vez por requisição (246 linhas).
     *
     * @return Collection<int, MunicipalityCoverageData>
     */
    public static function coverage(): Collection
    {
        return once(fn (): Collection => app(ChurchCoverageService::class)
            ->getAllMunicipalitiesCoverage()
            ->keyBy('municipalityId'));
    }

    /**
     * Ordena pela métrica calculada em PHP: monta um CASE com a posição de
     * cada id. Sem valor fica sempre por último. Funciona no SQLite e no MySQL.
     *
     * @param  Builder<Municipality>  $query
     * @return Builder<Municipality>
     */
    public static function orderByCoverage(Builder $query, string $field, string $direction): Builder
    {
        $ids = self::coverage()
            ->sort(function (MunicipalityCoverageData $a, MunicipalityCoverageData $b) use ($field, $direction): int {
                if ($a->{$field} === null || $b->{$field} === null) {
                    return ($a->{$field} === null) <=> ($b->{$field} === null);
                }

                return $direction === 'desc' ? $b->{$field} <=> $a->{$field} : $a->{$field} <=> $b->{$field};
            })
            ->keys()
            ->values();

        if ($ids->isEmpty()) {
            return $query->orderBy('municipalities.name');
        }

        $cases = $ids->map(fn (int $id, int $rank): string => "WHEN {$id} THEN {$rank}")->implode(' ');

        return $query
            ->orderByRaw("CASE municipalities.id {$cases} ELSE {$ids->count()} END")
            ->orderBy('municipalities.name');
    }

    private static function coverageColumn(string $field, string $label): Tables\Columns\TextColumn
    {
        return Tables\Columns\TextColumn::make($field)
            ->label($label)
            ->state(fn (Municipality $record): int|float|null => self::coverage()->get($record->id)?->{$field})
            ->placeholder('—')
            ->sortable(query: fn (Builder $query, string $direction): Builder => self::orderByCoverage($query, $field, $direction))
            ->alignEnd();
    }

    private static function shortLabel(StrategyIndicator $indicator): string
    {
        return match ($indicator) {
            StrategyIndicator::POPULATION_GROWTH => 'Crescimento',
            StrategyIndicator::RELIGION_EVANGELICAL_PCT_2022 => '% evangélicos',
            StrategyIndicator::RELIGION_BAPTIST_PCT_2010 => '% batistas (2010)',
            StrategyIndicator::PIB_PER_CAPITA => 'PIB per capita',
            default => $indicator->label(),
        };
    }
}
