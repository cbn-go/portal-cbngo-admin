<?php

namespace App\Filament\Pages;

use App\Models\InstitutionalSetting;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;

/**
 * @property Form $form
 */
class ManageInstitutionalSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Dados Institucionais';

    protected static ?string $title = 'Dados Institucionais';

    protected static ?string $slug = 'institutional-settings';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.manage-institutional-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active && $user->role->hasAdminPanelAccess();
    }

    public function mount(): void
    {
        $setting = InstitutionalSetting::current();
        $this->form->fill($setting->attributesToArray());
    }

    public function form(Form $form): Form
    {
        $setting = InstitutionalSetting::current();

        return $form
            ->schema([
                Forms\Components\Section::make('Canais de Contato Oficiais')
                    ->description('Canais de atendimento ao público e às igrejas filiadas.')
                    ->schema([
                        Forms\Components\TextInput::make('email')
                            ->label('E-mail Geral')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->placeholder('contato@cbngo.org.br'),

                        Forms\Components\TextInput::make('phone')
                            ->label('Telefone Fixo')
                            ->tel()
                            ->mask('(99) 9999-9999')
                            ->placeholder('(62) 3223-0000')
                            ->maxLength(30),

                        Forms\Components\TextInput::make('whatsapp')
                            ->label('WhatsApp Oficial')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->placeholder('(62) 98100-0000')
                            ->maxLength(30)
                            ->helperText('Utilizado para atendimento e dúvidas no portal público.'),

                        Forms\Components\TextInput::make('office_hours')
                            ->label('Horário de Atendimento')
                            ->placeholder('Segunda a Sexta, das 08h às 17h')
                            ->maxLength(150),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Endereço Oficial da Sede')
                    ->description('Endereço administrativo conforme consolidado no Art. 2º do Estatuto da CBN-GO.')
                    ->schema([
                        Forms\Components\TextInput::make('postal_code')
                            ->label('CEP')
                            ->mask('99999-999')
                            ->placeholder('74020-045')
                            ->maxLength(10)
                            ->suffixAction(
                                Forms\Components\Actions\Action::make('searchCep')
                                    ->label('Buscar CEP')
                                    ->icon('heroicon-m-magnifying-glass')
                                    ->tooltip('Buscar logradouro e bairro via ViaCEP')
                                    ->action(function (Forms\Set $set, ?string $state) {
                                        $cep = preg_replace('/\D/', '', (string) $state);
                                        if (strlen($cep) !== 8) {
                                            Notification::make()
                                                ->warning()
                                                ->title('CEP inválido')
                                                ->body('Informe um CEP com 8 dígitos.')
                                                ->send();

                                            return;
                                        }

                                        try {
                                            $response = Http::timeout(3)->get("https://viacep.com.br/ws/{$cep}/json/");
                                            if ($response->successful()) {
                                                $data = $response->json();
                                                if (! isset($data['erro'])) {
                                                    $set('street', $data['logradouro'] ?? '');
                                                    $set('district', $data['bairro'] ?? '');
                                                    $set('city', $data['localidade'] ?? 'Goiânia');
                                                    $set('state', $data['uf'] ?? 'GO');

                                                    Notification::make()
                                                        ->success()
                                                        ->title('Endereço preenchido com sucesso!')
                                                        ->send();
                                                } else {
                                                    Notification::make()
                                                        ->warning()
                                                        ->title('CEP não localizado no ViaCEP.')
                                                        ->send();
                                                }
                                            }
                                        } catch (\Throwable $e) {
                                            Notification::make()
                                                ->warning()
                                                ->title('Serviço ViaCEP temporariamente indisponível')
                                                ->body('Por favor, preencha o logradouro manualmente.')
                                                ->send();
                                        }
                                    })
                            ),

                        Forms\Components\TextInput::make('street')
                            ->label('Logradouro')
                            ->placeholder('Ex: Rua 04')
                            ->maxLength(200)
                            ->columnSpan(2),

                        Forms\Components\TextInput::make('number')
                            ->label('Número')
                            ->placeholder('Ex: 515')
                            ->maxLength(30),

                        Forms\Components\TextInput::make('complement')
                            ->label('Complemento')
                            ->placeholder('Ex: 12º andar, sala 1.207, Ed. Parthenon Center')
                            ->maxLength(150),

                        Forms\Components\TextInput::make('district')
                            ->label('Bairro / Setor')
                            ->placeholder('Ex: Setor Central')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('city')
                            ->label('Cidade')
                            ->required()
                            ->default('Goiânia')
                            ->maxLength(100),

                        Forms\Components\TextInput::make('state')
                            ->label('Estado (UF)')
                            ->required()
                            ->default('GO')
                            ->maxLength(2),

                        Forms\Components\TextInput::make('maps_url')
                            ->label('Link do Google Maps ("Como chegar")')
                            ->url()
                            ->placeholder('https://maps.google.com/?q=...')
                            ->columnSpanFull()
                            ->helperText('Link completo de rota no Google Maps para o botão do site.'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Redes Sociais Oficiais')
                    ->description('Links dos perfis institucionais. Campos em branco ocultam automaticamente o respectivo ícone no site público.')
                    ->schema([
                        Forms\Components\TextInput::make('instagram_url')
                            ->label('Instagram')
                            ->placeholder('https://www.instagram.com/cbngoias/')
                            ->regex('/^(https?:\/\/)?(www\.)?instagram\.com\/.+/i')
                            ->validationMessages([
                                'regex' => 'Informe uma URL válida do Instagram (ex: https://www.instagram.com/cbngoias/).',
                            ])
                            ->maxLength(255),

                        Forms\Components\TextInput::make('facebook_url')
                            ->label('Facebook')
                            ->placeholder('https://www.facebook.com/cbngoias/')
                            ->regex('/^(https?:\/\/)?(www\.)?facebook\.com\/.+/i')
                            ->validationMessages([
                                'regex' => 'Informe uma URL válida do Facebook (ex: https://www.facebook.com/cbngoias/).',
                            ])
                            ->maxLength(255),

                        Forms\Components\TextInput::make('youtube_url')
                            ->label('YouTube')
                            ->placeholder('https://www.youtube.com/@cbngoias')
                            ->regex('/^(https?:\/\/)?(www\.)?youtube\.com\/.+/i')
                            ->validationMessages([
                                'regex' => 'Informe uma URL válida do YouTube (ex: https://www.youtube.com/@cbngoias).',
                            ])
                            ->maxLength(255),

                        Forms\Components\TextInput::make('tiktok_url')
                            ->label('TikTok (opcional)')
                            ->placeholder('https://www.tiktok.com/@cbngoias')
                            ->regex('/^(https?:\/\/)?(www\.)?tiktok\.com\/.+/i')
                            ->validationMessages([
                                'regex' => 'Informe uma URL válida do TikTok (ex: https://www.tiktok.com/@cbngoias).',
                            ])
                            ->maxLength(255),

                        Forms\Components\TextInput::make('spotify_url')
                            ->label('Spotify (opcional)')
                            ->placeholder('https://open.spotify.com/...')
                            ->regex('/^(https?:\/\/)?(open\.)?spotify\.com\/.+/i')
                            ->validationMessages([
                                'regex' => 'Informe uma URL válida do Spotify (ex: https://open.spotify.com/...).',
                            ])
                            ->maxLength(255),

                        Forms\Components\TextInput::make('linkedin_url')
                            ->label('LinkedIn (opcional)')
                            ->placeholder('https://www.linkedin.com/company/...')
                            ->regex('/^(https?:\/\/)?(www\.)?linkedin\.com\/.+/i')
                            ->validationMessages([
                                'regex' => 'Informe uma URL válida do LinkedIn (ex: https://www.linkedin.com/company/...).',
                            ])
                            ->maxLength(255),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Pré-visualização do Rodapé')
                    ->description('Simulação de como os dados atuais e canais aparecerão aos visitantes do site.')
                    ->schema([
                        Forms\Components\ViewField::make('preview')
                            ->view('filament.pages.institutional-settings.preview')
                            ->viewData(['setting' => $setting])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Auditoria')
                    ->schema([
                        Forms\Components\Placeholder::make('last_update_info')
                            ->label('Registro de Modificações')
                            ->content(function () use ($setting): string {
                                if ($setting->updater && $setting->updated_at) {
                                    return "Última alteração por {$setting->updater->name} em ".$setting->updated_at->format('d/m/Y \à\s H:i');
                                }

                                if ($setting->updated_at) {
                                    return 'Última alteração em '.$setting->updated_at->format('d/m/Y \à\s H:i');
                                }

                                return 'Nenhuma alteração registrada.';
                            }),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $state['updated_by'] = auth()->id();

        $setting = InstitutionalSetting::current();
        $setting->update($state);

        Notification::make()
            ->title('Dados atualizados')
            ->body('O site já exibe as novas informações.')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar Alterações')
                ->icon('heroicon-o-check')
                ->submit('save'),
        ];
    }
}
