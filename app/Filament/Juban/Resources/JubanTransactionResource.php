<?php

namespace App\Filament\Juban\Resources;

use App\Enums\JubanPaymentMethod;
use App\Enums\JubanTransactionType;
use App\Filament\Juban\Resources\JubanTransactionResource\Pages;
use App\Models\JubanCategory;
use App\Models\JubanReport;
use App\Models\JubanTransaction;
use App\Services\Juban\JubanTransactionExportService;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JubanTransactionResource extends Resource
{
    protected static ?string $model = JubanTransaction::class;

    protected static ?string $slug = 'lancamentos';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static ?string $navigationGroup = 'Tesouraria';

    protected static ?string $navigationLabel = 'Lançamentos';

    protected static ?string $modelLabel = 'Lançamento';

    protected static ?string $pluralModelLabel = 'Lançamentos';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'description';

    public const INCOMPATIBLE_CATEGORY_MESSAGE = 'A categoria selecionada não é compatível com o tipo do lançamento.';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Lançamento')
                    ->schema([
                        Forms\Components\ToggleButtons::make('type')
                            ->label('Tipo')
                            ->options(JubanTransactionType::class)
                            ->inline()
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('category_id', null)),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor')
                            ->prefix('R$')
                            ->numeric()
                            ->step('0.01')
                            ->required()
                            ->rules(['gt:0'])
                            ->validationMessages([
                                'gt' => 'O valor deve ser maior que zero.',
                            ]),

                        Forms\Components\DatePicker::make('occurred_on')
                            ->label('Data')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default(now())
                            ->required()
                            ->rules([
                                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                    if ($value && JubanReport::isDateLocked($value)) {
                                        $fail('O período contábil selecionado está fechado para relatório trimestral e não aceita novos lançamentos ou alterações.');
                                    }
                                },
                            ]),

                        Forms\Components\Select::make('category_id')
                            ->label('Categoria')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get, ?JubanTransaction $record): Builder => static::categoryOptionsQuery($query, $get('type'), $record),
                            )
                            ->native(false)
                            ->preload()
                            ->searchable()
                            ->required()
                            ->disabled(fn (Get $get): bool => static::typeFromState($get('type')) === null)
                            ->helperText(fn (Get $get): ?string => static::typeFromState($get('type')) === null ? 'Escolha o tipo para ver as categorias.' : null)
                            ->rules([
                                fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                                    $type = static::typeFromState($get('type'));
                                    $category = filled($value) ? JubanCategory::query()->find($value) : null;

                                    if ($type !== null && $category !== null && ! $category->canAcceptType($type)) {
                                        $fail(static::INCOMPATIBLE_CATEGORY_MESSAGE);
                                    }
                                },
                            ]),

                        Forms\Components\Select::make('payment_method')
                            ->label('Forma de pagamento')
                            ->options(JubanPaymentMethod::class)
                            ->native(false),

                        Forms\Components\TextInput::make('description')
                            ->label('Descrição')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex.: Inscrições do Congresso Estadual')
                            ->columnSpanFull(),

                        Forms\Components\TextInput::make('document_ref')
                            ->label('Referência / documento')
                            ->maxLength(100)
                            ->placeholder('Nº do recibo, nota fiscal ou comprovante'),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('occurred_on')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Categoria')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->formatStateUsing(fn (JubanTransaction $record): string => static::formatSignedBrl($record))
                    ->color(fn (JubanTransaction $record): string => $record->isReceita() ? 'success' : 'danger')
                    ->alignEnd()
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (JubanTransaction $record): string => $record->description),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('Forma de pagamento')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('document_ref')
                    ->label('Referência')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Lançado por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('occurred_on', 'desc')
            ->filters([
                Tables\Filters\Filter::make('periodo')
                    ->label('Período')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('De')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Até')
                            ->native(false)
                            ->displayFormat('d/m/Y'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('occurred_on', '>=', Carbon::parse($date)->toDateString()))
                            ->when($data['until'] ?? null, fn (Builder $q, string $date): Builder => $q->whereDate('occurred_on', '<=', Carbon::parse($date)->toDateString()));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (filled($data['from'] ?? null)) {
                            $indicators[] = Indicator::make('De '.Carbon::parse($data['from'])->format('d/m/Y'))->removeField('from');
                        }

                        if (filled($data['until'] ?? null)) {
                            $indicators[] = Indicator::make('Até '.Carbon::parse($data['until'])->format('d/m/Y'))->removeField('until');
                        }

                        return $indicators;
                    }),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(JubanTransactionType::class),

                Tables\Filters\SelectFilter::make('category_id')
                    ->label('Categoria')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TrashedFilter::make()
                    ->visible(fn (): bool => static::canSeeTrashed()),
            ])
            ->headerActions([
                Tables\Actions\Action::make('export')
                    ->label('Exportar CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->can('export', JubanTransaction::class) ?? false)
                    ->action(function (HasTable $livewire, JubanTransactionExportService $service): StreamedResponse {
                        /** @var Builder<JubanTransaction> $query */
                        $query = $livewire->getFilteredTableQuery();

                        return $service->exportCsv($query);
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ]);
    }

    /**
     * Categorias ativas compatíveis com o tipo; na edição, mantém a categoria atual
     * mesmo que tenha sido desativada depois.
     *
     * @param  Builder<JubanCategory>  $query
     * @return Builder<JubanCategory>
     */
    public static function categoryOptionsQuery(Builder $query, mixed $typeState, ?JubanTransaction $record = null): Builder
    {
        $type = static::typeFromState($typeState);

        if ($type === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query
            ->forType($type)
            ->where(function (Builder $q) use ($record): void {
                $q->where('is_active', true);

                if ($record?->category_id !== null) {
                    $q->orWhere('id', $record->category_id);
                }
            })
            ->orderBy('name');
    }

    public static function typeFromState(mixed $state): ?JubanTransactionType
    {
        if ($state instanceof JubanTransactionType) {
            return $state;
        }

        return is_string($state) ? JubanTransactionType::tryFrom($state) : null;
    }

    public static function formatBrl(float|string $amount): string
    {
        return 'R$ '.number_format((float) $amount, 2, ',', '.');
    }

    public static function formatSignedBrl(JubanTransaction $record): string
    {
        return ($record->isDespesa() ? '- ' : '').static::formatBrl($record->amount);
    }

    /**
     * @return Builder<JubanTransaction>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<JubanTransaction> $query */
        $query = parent::getEloquentQuery()->with(['category', 'creator']);

        // Só o SuperAdmin restaura lançamentos excluídos; para ele o TrashedFilter
        // decide se a lixeira aparece. Os demais nunca enxergam excluídos.
        if (static::canSeeTrashed()) {
            $query->withoutGlobalScopes([SoftDeletingScope::class]);
        }

        return $query;
    }

    public static function canSeeTrashed(): bool
    {
        return auth()->user()?->role->isSuperAdmin() ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJubanTransactions::route('/'),
            'create' => Pages\CreateJubanTransaction::route('/create'),
            'edit' => Pages\EditJubanTransaction::route('/{record}/edit'),
        ];
    }
}
