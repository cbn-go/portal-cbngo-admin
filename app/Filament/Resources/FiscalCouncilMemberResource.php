<?php

namespace App\Filament\Resources;

use App\Enums\FiscalCouncilSeat;
use App\Filament\Resources\FiscalCouncilMemberResource\Pages;
use App\Models\FiscalCouncilMember;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FiscalCouncilMemberResource extends Resource
{
    protected static ?string $model = FiscalCouncilMember::class;

    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static ?string $navigationGroup = 'Secretaria';

    protected static ?string $navigationLabel = 'Conselho Fiscal';

    protected static ?string $modelLabel = 'Membro do Conselho Fiscal';

    protected static ?string $pluralModelLabel = 'Conselho Fiscal';

    protected static ?int $navigationSort = 3;

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        /** @var FiscalCouncilMember|null $record */
        if (! $record) {
            return static::getModelLabel();
        }

        return $record->seat->getLabel().' - '.$record->user->name;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->role->isSuperAdmin() ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Membro e Assento')
                    ->description('Defina o usuário associado, o assento eleito e as qualificações para o parecer.')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('Usuário')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Usuário que terá acesso ao painel do Conselho Fiscal.'),

                        Forms\Components\Select::make('seat')
                            ->label('Assento')
                            ->options(FiscalCouncilSeat::class)
                            ->required()
                            ->native(false)
                            ->rules([
                                fn (Get $get, ?FiscalCouncilMember $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                                    $isActive = (bool) $get('active');
                                    if ($isActive && filled($value)) {
                                        $seatVal = $value instanceof FiscalCouncilSeat ? $value->value : (string) $value;
                                        $conflict = FiscalCouncilMember::query()
                                            ->where('seat', $seatVal)
                                            ->where('active', true)
                                            ->when($record?->id, fn (Builder $q) => $q->where('id', '!=', $record->id))
                                            ->exists();

                                        if ($conflict) {
                                            $fail('Já existe um membro ativo ocupando este assento.');
                                        }
                                    }
                                },
                            ]),

                        Forms\Components\TextInput::make('title')
                            ->label('Tratamento / Título')
                            ->maxLength(30)
                            ->placeholder('Ex: Pr., Dc., Sr., Sra.')
                            ->helperText('Abreviação que antecede o nome no parecer oficial.'),

                        Forms\Components\TextInput::make('qualification')
                            ->label('Qualificação profissional / eclesiástica')
                            ->maxLength(150)
                            ->placeholder('Ex: Técnico em contabilidade, Contador')
                            ->helperText('Linha exibida abaixo do nome no parecer.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Mandato e Ativação')
                    ->schema([
                        Forms\Components\DatePicker::make('term_starts_on')
                            ->label('Início do Mandato')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default('2026-01-01'),

                        Forms\Components\DatePicker::make('term_ends_on')
                            ->label('Fim do Mandato')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->default('2028-12-31'),

                        Forms\Components\Toggle::make('active')
                            ->label('Membro Ativo')
                            ->default(true)
                            ->helperText('Apenas membros ativos podem assinar pareceres e ocupar o assento no mandato.'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Membro')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('seat')
                    ->label('Assento')
                    ->badge()
                    ->sortable(),

                Tables\Columns\TextColumn::make('title')
                    ->label('Tratamento')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('qualification')
                    ->label('Qualificação')
                    ->limit(30)
                    ->toggleable(),

                Tables\Columns\TextColumn::make('term_starts_on')
                    ->label('Início')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('term_ends_on')
                    ->label('Fim')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('seat')
            ->filters([
                Tables\Filters\SelectFilter::make('seat')
                    ->label('Assento')
                    ->options(FiscalCouncilSeat::class),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFiscalCouncilMembers::route('/'),
            'create' => Pages\CreateFiscalCouncilMember::route('/create'),
            'edit' => Pages\EditFiscalCouncilMember::route('/{record}/edit'),
        ];
    }
}
