<?php

namespace App\Filament\Resources;

use App\Enums\ProjectCategory;
use App\Enums\PublishStatus;
use App\Filament\Resources\SocialProjectResource\Pages;
use App\Models\Church;
use App\Models\SocialProject;
use App\Models\User;
use App\Support\MediaVariants;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class SocialProjectResource extends Resource
{
    protected static ?string $model = SocialProject::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = 'Conteúdo';

    protected static ?string $navigationLabel = 'Projetos Sociais';

    protected static ?string $modelLabel = 'Projeto Social';

    protected static ?string $pluralModelLabel = 'Projetos Sociais';

    protected static ?int $navigationSort = 3;

    protected static bool $hasTitleCaseModelLabel = false;

    public static function isAdmin(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user?->role->hasAdminPanelAccess() ?? false;
    }

    /**
     * Opções de status: a administração vê todas; o representante só pode
     * deixar como rascunho ou enviar para revisão.
     *
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        if (self::isAdmin()) {
            $options = [];

            foreach (PublishStatus::cases() as $status) {
                $options[$status->value] = $status->getLabel();
            }

            return $options;
        }

        return [
            PublishStatus::DRAFT->value => 'Rascunho',
            PublishStatus::PENDING_REVIEW->value => 'Enviar para revisão',
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Placeholder::make('review_notes_display')
                    ->label('Ajustes solicitados pela administração')
                    ->content(fn (?SocialProject $record): HtmlString => new HtmlString(nl2br(e((string) $record?->review_notes))))
                    ->visible(fn (?SocialProject $record): bool => filled($record?->review_notes))
                    ->columnSpanFull(),

                Forms\Components\Section::make('Identificação')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label('Nome do projeto')
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
                            ->unique(SocialProject::class, 'slug', ignoreRecord: true)
                            ->alphaDash(),

                        Forms\Components\Select::make('category')
                            ->label('Categoria (Pacto de Lausanne)')
                            ->options(ProjectCategory::class)
                            ->required()
                            ->native(false)
                            ->live()
                            ->helperText(function (Get $get): string {
                                $category = $get('category');
                                $category = $category instanceof ProjectCategory ? $category : ProjectCategory::tryFrom((string) $category);

                                return $category === null
                                    ? 'Social, Cultural, Educação ou Liderança: as dimensões da missão integral.'
                                    : $category->description().' ('.$category->lausanneReference().')';
                            }),

                        Forms\Components\Select::make('church_id')
                            ->label('Igreja')
                            ->relationship('church', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (filled($state)) {
                                    $set('city', Church::query()->whereKey($state)->value('city'));
                                }
                            })
                            ->visible(fn (): bool => self::isAdmin()),

                        Forms\Components\Textarea::make('summary')
                            ->label('Resumo')
                            ->required()
                            ->maxLength(300)
                            ->rows(2)
                            ->columnSpanFull()
                            ->helperText('Uma ou duas frases. Aparece nos cards da página de projetos.'),

                        Forms\Components\RichEditor::make('description')
                            ->label('Descrição do projeto')
                            ->required()
                            ->columnSpanFull()
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'underline',
                                'undo',
                            ])
                            ->fileAttachmentsDisk(MediaVariants::diskName())
                            ->fileAttachmentsDirectory('projects/attachments')
                            ->fileAttachmentsVisibility('public'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Mídia')
                    ->schema([
                        Forms\Components\FileUpload::make('cover_image')
                            ->label('Imagem de capa')
                            ->image()
                            ->disk(MediaVariants::diskName())
                            ->directory('projects/covers')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeMode('cover')
                            ->imageResizeTargetWidth('1600')
                            ->imageResizeTargetHeight('900')
                            ->columnSpanFull()
                            ->helperText('JPG, PNG ou WEBP até 5MB, proporção 16:9. Usada nos cards e no topo da página do projeto.'),

                        Forms\Components\FileUpload::make('gallery')
                            ->label('Galeria de fotos')
                            ->image()
                            ->multiple()
                            ->reorderable()
                            ->disk(MediaVariants::diskName())
                            ->directory('projects/galleries')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->maxFiles(10)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->columnSpanFull()
                            ->helperText('Até 10 fotos (JPG, PNG, WEBP até 5MB cada).'),
                    ]),

                Forms\Components\Section::make('Público e local')
                    ->schema([
                        Forms\Components\TextInput::make('target_audience')
                            ->label('Público atendido')
                            ->maxLength(255)
                            ->placeholder('Ex.: crianças de 6 a 12 anos do bairro'),

                        Forms\Components\TextInput::make('beneficiaries_count')
                            ->label('Número de beneficiados')
                            ->numeric()
                            ->integer()
                            ->minValue(0),

                        Forms\Components\TextInput::make('city')
                            ->label('Cidade')
                            ->required()
                            ->maxLength(120)
                            ->default(function (): ?string {
                                /** @var User|null $user */
                                $user = Auth::user();

                                return $user?->church?->city;
                            })
                            ->helperText('Preenchida com a cidade da igreja; altere se o projeto acontece em outra cidade.'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Período e contato')
                    ->schema([
                        Forms\Components\DatePicker::make('started_at')
                            ->label('Início')
                            ->required()
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Forms\Components\DatePicker::make('ended_at')
                            ->label('Término')
                            ->native(false)
                            ->displayFormat('d/m/Y')
                            ->rules([
                                fn (Get $get): \Closure => function (string $attribute, mixed $value, \Closure $fail) use ($get): void {
                                    $startedAt = $get('started_at');
                                    if (filled($startedAt) && filled($value) && Carbon::parse($value)->lt(Carbon::parse($startedAt))) {
                                        $fail('O término deve ser igual ou posterior ao início.');
                                    }
                                },
                            ])
                            ->helperText('Deixe vazio se o projeto está em andamento.'),

                        Forms\Components\TextInput::make('contact_name')
                            ->label('Responsável pelo contato')
                            ->maxLength(120),

                        Forms\Components\TextInput::make('contact_phone')
                            ->label('Telefone / WhatsApp')
                            ->tel()
                            ->maxLength(20)
                            ->mask('(99) 99999-9999')
                            ->helperText('Com número, o site exibe um botão de WhatsApp.'),

                        Forms\Components\TextInput::make('contact_email')
                            ->label('E-mail de contato')
                            ->email()
                            ->maxLength(255),

                        Forms\Components\Textarea::make('how_to_help')
                            ->label('Como ajudar')
                            ->rows(3)
                            ->maxLength(2000)
                            ->columnSpanFull()
                            ->helperText('Voluntariado, doações, horários e o que mais a comunidade precisa saber.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Publicação')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options(fn (): array => self::statusOptions())
                            ->in(fn (): array => array_keys(self::statusOptions()))
                            ->default(PublishStatus::DRAFT->value)
                            ->required()
                            ->native(false)
                            ->helperText(fn (): ?string => self::isAdmin()
                                ? null
                                : 'Envie para revisão quando o projeto estiver pronto. A administração aprova a publicação. Alterações em um projeto publicado voltam para revisão.'),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Data de publicação')
                            ->native(false)
                            ->displayFormat('d/m/Y H:i')
                            ->seconds(false)
                            ->visible(fn (): bool => self::isAdmin())
                            ->helperText('Vazio = preenchida ao publicar.'),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Destacar no portal')
                            ->inline(false)
                            ->visible(fn (): bool => self::isAdmin())
                            ->helperText('Projetos em destaque aparecem primeiro e na página inicial.'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('cover_image')
                    ->label('Capa')
                    ->disk(MediaVariants::diskName())
                    ->height(40)
                    ->width(71),

                Tables\Columns\TextColumn::make('title')
                    ->label('Projeto')
                    ->searchable()
                    ->limit(45)
                    ->description(fn (SocialProject $record): ?string => $record->church?->name),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge(),

                Tables\Columns\TextColumn::make('period')
                    ->label('Período')
                    ->state(fn (SocialProject $record): string => $record->isOngoing() ? 'Em andamento' : 'Encerrado')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Em andamento' ? 'success' : 'gray'),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Destaque')
                    ->boolean()
                    ->trueIcon('heroicon-s-star')
                    ->falseIcon('heroicon-o-star')
                    ->visible(fn (): bool => self::isAdmin()),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Publicado em')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(ProjectCategory::class),

                Tables\Filters\SelectFilter::make('church_id')
                    ->label('Igreja')
                    ->relationship('church', 'name')
                    ->searchable()
                    ->preload()
                    ->visible(fn (): bool => self::isAdmin()),

                Tables\Filters\SelectFilter::make('city')
                    ->label('Cidade')
                    ->options(fn (): array => SocialProject::query()
                        ->distinct()
                        ->orderBy('city')
                        ->pluck('city', 'city')
                        ->all()),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Status')
                    ->options(PublishStatus::class),

                Tables\Filters\TernaryFilter::make('ongoing')
                    ->label('Período')
                    ->placeholder('Todos')
                    ->trueLabel('Em andamento')
                    ->falseLabel('Encerrados')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where(fn (Builder $q) => $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', today())),
                        false: fn (Builder $query): Builder => $query->whereNotNull('ended_at')->whereDate('ended_at', '<', today()),
                    ),
            ])
            ->actions([
                self::approveAction(Tables\Actions\Action::class),
                self::returnAction(Tables\Actions\Action::class),
                Tables\Actions\Action::make('toggleFeatured')
                    ->label(fn (SocialProject $record): string => $record->is_featured ? 'Remover destaque' : 'Destacar')
                    ->icon(fn (SocialProject $record): string => $record->is_featured ? 'heroicon-o-star' : 'heroicon-s-star')
                    ->color('warning')
                    ->authorize(fn (SocialProject $record): bool => Gate::allows('feature', $record))
                    ->action(function (SocialProject $record): void {
                        $record->update(['is_featured' => ! $record->is_featured]);
                    }),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Ação "Aprovar e publicar", usada na listagem e na página de edição.
     *
     * @template T of Tables\Actions\Action|\Filament\Actions\Action
     *
     * @param  class-string<T>  $actionClass
     * @return T
     */
    public static function approveAction(string $actionClass): Tables\Actions\Action|Action
    {
        return $actionClass::make('approve')
            ->label('Aprovar e publicar')
            ->icon('heroicon-o-check-badge')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription('O projeto passa a aparecer no portal público.')
            ->visible(fn (SocialProject $record): bool => $record->status === PublishStatus::PENDING_REVIEW)
            ->authorize(fn (SocialProject $record): bool => Gate::allows('approve', $record))
            ->action(function (SocialProject $record): void {
                $record->approve();

                Notification::make()->title('Projeto aprovado e publicado.')->success()->send();
            });
    }

    /**
     * Ação "Devolver para ajustes", com o motivo para o representante.
     *
     * @template T of Tables\Actions\Action|\Filament\Actions\Action
     *
     * @param  class-string<T>  $actionClass
     * @return T
     */
    public static function returnAction(string $actionClass): Tables\Actions\Action|Action
    {
        return $actionClass::make('returnForAdjustments')
            ->label('Devolver para ajustes')
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->form([
                Forms\Components\Textarea::make('review_notes')
                    ->label('O que precisa ser ajustado?')
                    ->required()
                    ->rows(4)
                    ->maxLength(2000),
            ])
            ->visible(fn (SocialProject $record): bool => $record->status === PublishStatus::PENDING_REVIEW)
            ->authorize(fn (SocialProject $record): bool => Gate::allows('approve', $record))
            ->action(function (SocialProject $record, array $data): void {
                $record->returnForAdjustments((string) $data['review_notes']);

                Notification::make()->title('Projeto devolvido ao representante.')->success()->send();
            });
    }

    public static function getNavigationBadge(): ?string
    {
        if (! self::isAdmin()) {
            return null;
        }

        $pending = SocialProject::query()->pendingReview()->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Projetos aguardando revisão';
    }

    /**
     * @return Builder<SocialProject>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var Builder<SocialProject> $query */
        $query = parent::getEloquentQuery()->with(['church', 'author']);

        return $query;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSocialProjects::route('/'),
            'create' => Pages\CreateSocialProject::route('/create'),
            'edit' => Pages\EditSocialProject::route('/{record}/edit'),
        ];
    }
}
