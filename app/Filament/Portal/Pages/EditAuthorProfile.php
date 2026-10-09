<?php

namespace App\Filament\Portal\Pages;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Auth\EditProfile;
use Illuminate\Database\Eloquent\Model;

class EditAuthorProfile extends EditProfile
{
    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static ?string $navigationLabel = 'Meu Perfil de Autor';

    protected static ?string $navigationGroup = 'Minha Conta';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->role === UserRole::AUTHOR;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Dados da Conta de Acesso')
                    ->description('Informações fundamentais da conta institucional de articulista.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome Completo')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(User::class, 'email', ignorable: $this->getUser()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Identificação Pastoral & Biografia')
                    ->description('Apresentação pública do autor nos artigos e publicações da CBN-GO.')
                    ->schema([
                        Forms\Components\TextInput::make('pastoral_title')
                            ->label('Título Pastoral / Ministerial')
                            ->maxLength(50)
                            ->placeholder('Ex: Pastor, Pastora, Evangelista, Missionária, Teólogo'),

                        Forms\Components\FileUpload::make('avatar')
                            ->label('Foto de Perfil')
                            ->image()
                            ->disk('public')
                            ->directory('authors/avatars')
                            ->visibility('public')
                            ->avatar()
                            ->circleCropper()
                            ->maxSize(2048)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->helperText('Formatos: JPG, PNG, WEBP (máx. 2MB). Foto de rosto com recorte circular.'),

                        Forms\Components\Textarea::make('bio')
                            ->label('Biografia do Autor')
                            ->rows(4)
                            ->maxLength(1000)
                            ->columnSpanFull()
                            ->helperText('Resumo sobre seu chamado ministerial, atuação pastoral e formação teológica.'),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Redes Sociais & Presença Digital')
                    ->description('Canais oficiais do autor para exibição na assinatura de artigos.')
                    ->schema([
                        Forms\Components\TextInput::make('social_instagram')
                            ->label('Instagram')
                            ->maxLength(100)
                            ->placeholder('@seu_perfil ou https://instagram.com/seu_perfil'),

                        Forms\Components\TextInput::make('social_facebook')
                            ->label('Facebook')
                            ->maxLength(255)
                            ->url()
                            ->placeholder('https://facebook.com/seu_perfil'),

                        Forms\Components\TextInput::make('social_youtube')
                            ->label('Canal no YouTube')
                            ->maxLength(255)
                            ->url()
                            ->placeholder('https://youtube.com/@seu_canal'),

                        Forms\Components\TextInput::make('social_linkedin')
                            ->label('LinkedIn')
                            ->maxLength(255)
                            ->url()
                            ->placeholder('https://linkedin.com/in/seu_perfil'),

                        Forms\Components\TextInput::make('social_website')
                            ->label('Site / Blog Pessoal')
                            ->maxLength(255)
                            ->url()
                            ->placeholder('https://seusite.com.br')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Alteração de Senha (Opcional)')
                    ->description('Preencha apenas se desejar redefinir sua senha de acesso ao portal.')
                    ->schema([
                        Forms\Components\TextInput::make('password')
                            ->label('Nova Senha')
                            ->password()
                            ->nullable()
                            ->minLength(8)
                            ->same('password_confirmation')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? $state : null),

                        Forms\Components\TextInput::make('password_confirmation')
                            ->label('Confirmar Nova Senha')
                            ->password()
                            ->nullable()
                            ->dehydrated(false),
                    ])
                    ->columns(2)
                    ->collapsed(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var User $user */
        $user = $this->getUser();
        $authorProfile = $user->authorProfile;

        if ($authorProfile !== null) {
            $data['pastoral_title'] = $authorProfile->pastoral_title;
            $data['bio'] = $authorProfile->bio;
            $data['avatar'] = $authorProfile->avatar;

            $socialLinks = $authorProfile->social_links ?? [];
            $data['social_instagram'] = $socialLinks['instagram'] ?? null;
            $data['social_facebook'] = $socialLinks['facebook'] ?? null;
            $data['social_youtube'] = $socialLinks['youtube'] ?? null;
            $data['social_linkedin'] = $socialLinks['linkedin'] ?? null;
            $data['social_website'] = $socialLinks['website'] ?? null;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $record */
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
        ];

        if (filled($data['password'] ?? null)) {
            $userData['password'] = $data['password'];
        }

        $record->update($userData);

        $socialLinks = array_filter([
            'instagram' => $data['social_instagram'] ?? null,
            'facebook' => $data['social_facebook'] ?? null,
            'youtube' => $data['social_youtube'] ?? null,
            'linkedin' => $data['social_linkedin'] ?? null,
            'website' => $data['social_website'] ?? null,
        ]);

        $record->authorProfile()->updateOrCreate(
            ['user_id' => $record->id],
            [
                'pastoral_title' => $data['pastoral_title'] ?? null,
                'bio' => $data['bio'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'social_links' => count($socialLinks) > 0 ? $socialLinks : null,
            ]
        );

        return $record;
    }
}
