<?php

namespace App\Filament\Resources;

use App\Enums\NoticePriority;
use App\Filament\Resources\NoticeResource\Pages;
use App\Models\Notice;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class NoticeResource extends Resource
{
    protected static ?string $model = Notice::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationGroup = 'Comunicação';

    protected static ?string $navigationLabel = 'Avisos e Comunicados';

    protected static ?string $modelLabel = 'Aviso';

    protected static ?string $pluralModelLabel = 'Avisos';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Conteúdo do Comunicado')
                    ->description('Defina o título, o identificador de URL e o conteúdo explicativo do aviso institucional.')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Título do Comunicado')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create' && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->label('Slug / Identificador URL')
                            ->required()
                            ->maxLength(255)
                            ->unique(Notice::class, 'slug', ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Identificador único amigável utilizado nas rotas do portal.'),

                        Forms\Components\RichEditor::make('content')
                            ->label('Conteúdo Rico / Texto Explicativo')
                            ->required()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Ação Opcional (Call to Action)')
                    ->description('Adicione um link externo ou botão direcionando para uma ação opcional.')
                    ->schema([
                        Forms\Components\TextInput::make('action_url')
                            ->label('URL de Destino')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://cbngo.com.br/edital'),

                        Forms\Components\TextInput::make('action_label')
                            ->label('Texto do Botão / Ação')
                            ->maxLength(100)
                            ->placeholder('Ex: Acessar Edital, Saiba Mais'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Prioridade e Vigência')
                    ->description('Controle o grau de urgência e o intervalo de datas para exibição automática no site.')
                    ->schema([
                        Forms\Components\Select::make('priority')
                            ->label('Prioridade')
                            ->options(NoticePriority::class)
                            ->default(NoticePriority::NORMAL)
                            ->required()
                            ->native(false),

                        Forms\Components\DateTimePicker::make('starts_at')
                            ->label('Início da Exibição')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->helperText('Deixe vazio para exibição imediata.'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiração (Encerramento Automático)')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $startsAt = $get('starts_at');
                                    if (filled($startsAt) && filled($value)) {
                                        if (Carbon::parse($value)->lt(Carbon::parse($startsAt))) {
                                            $fail('A data de expiração deve ser igual ou posterior à data de início.');
                                        }
                                    }
                                },
                            ])
                            ->helperText('Deixe vazio para não expirar automaticamente.'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Status Ativo')
                            ->helperText('Quando desativado, o aviso não é exibido mesmo se estiver no período de vigência.')
                            ->default(true)
                            ->inline(false),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->tooltip(fn (Notice $record): string => $record->title),

                Tables\Columns\TextColumn::make('priority')
                    ->label('Prioridade')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('validity_status')
                    ->label('Vigência')
                    ->badge(),

                Tables\Columns\TextColumn::make('starts_at')
                    ->label('Início')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expiração')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Ativo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Autor')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('priority')
                    ->label('Prioridade')
                    ->options(NoticePriority::class),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Ativo'),

                Tables\Filters\SelectFilter::make('validity')
                    ->label('Vigência')
                    ->options([
                        'active' => 'Vigentes',
                        'scheduled' => 'Agendados',
                        'expired' => 'Expirados',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        /** @var Builder<Notice> $noticeQuery */
                        $noticeQuery = $query;

                        return match ($data['value'] ?? null) {
                            'active' => $noticeQuery->currentlyActive(),
                            'scheduled' => $noticeQuery->scheduled(),
                            'expired' => $noticeQuery->expired(),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotices::route('/'),
            'create' => Pages\CreateNotice::route('/create'),
            'edit' => Pages\EditNotice::route('/{record}/edit'),
        ];
    }
}
