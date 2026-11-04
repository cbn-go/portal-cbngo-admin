<?php

namespace App\Filament\Ormiban\Pages\Auth;

use App\Models\User;
use App\Rules\CpfRule;
use App\Support\Cpf;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class OrmibanLogin extends BaseLogin
{
    protected static string $layout = 'filament.layouts.auth';

    protected static string $view = 'filament.ormiban.pages.auth.login';

    public ?int $attemptsRemaining = null;

    public bool $hasAuthFailed = false;

    public function mount(): void
    {
        parent::mount();

        $this->data['remember'] = false;

        $key = $this->getRateLimitKey('authenticate', static::class);
        $this->attemptsRemaining = max(0, RateLimiter::remaining($key, 5));
    }

    public function authenticate(): ?LoginResponse
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return null;
        }

        $data = $this->form->getState();
        $remember = (bool) ($this->data['remember'] ?? false);

        $rawCpf = (string) ($data['cpf'] ?? '');
        $cleanCpf = Cpf::normalize($rawCpf);

        if ($cleanCpf === null || ! Cpf::isValid($cleanCpf)) {
            $key = $this->getRateLimitKey('authenticate', static::class);
            $this->attemptsRemaining = max(0, RateLimiter::remaining($key, 5));
            $this->hasAuthFailed = true;

            throw ValidationException::withMessages([
                'data.cpf' => 'O CPF informado é inválido.',
            ]);
        }

        /** @var User|null $user */
        $user = User::query()->where('cpf', $cleanCpf)->first();

        if ($user === null) {
            $key = $this->getRateLimitKey('authenticate', static::class);
            $this->attemptsRemaining = max(0, RateLimiter::remaining($key, 5));
            $this->hasAuthFailed = true;

            throw ValidationException::withMessages([
                'data.cpf' => __('As credenciais informadas não coincidem com nossos registros.'),
            ]);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'data.cpf' => __('Sua conta está desativada. Entre em contato com a administração.'),
            ]);
        }

        // Primeiro acesso: usuário precisa definir senha (must_set_password = true ou password nulo)
        if ((bool) $user->must_set_password || $user->password === null) {
            Filament::auth()->login($user, $remember);
            session()->regenerate();
            $this->clearRateLimiter('authenticate', static::class);

            return new class implements LoginResponse
            {
                public function toResponse($request)
                {
                    return redirect()->to('/ormiban/definir-senha');
                }
            };
        }

        // Acesso regular: valida a senha
        $password = (string) ($data['password'] ?? '');
        if (blank($password) || ! Hash::check($password, (string) $user->password)) {
            $key = $this->getRateLimitKey('authenticate', static::class);
            $this->attemptsRemaining = max(0, RateLimiter::remaining($key, 5));
            $this->hasAuthFailed = true;

            throw ValidationException::withMessages([
                'data.cpf' => __('As credenciais informadas não coincidem com nossos registros.'),
            ]);
        }

        Filament::auth()->login($user, $remember);
        session()->regenerate();
        $this->clearRateLimiter('authenticate', static::class);

        if (! $user->canAccessPanel(Filament::getCurrentPanel())) {
            $destinationUrl = $user->getDefaultPanelUrl();

            return new class($destinationUrl) implements LoginResponse
            {
                public function __construct(private readonly string $url) {}

                public function toResponse($request)
                {
                    return redirect()->to($this->url);
                }
            };
        }

        return new class implements LoginResponse
        {
            public function toResponse($request)
            {
                return redirect()->intended('/ormiban');
            }
        };
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getCpfFormComponent(),
                $this->getPasswordFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getCpfFormComponent(): Component
    {
        return TextInput::make('cpf')
            ->label('CPF')
            ->mask('999.999.999-99')
            ->placeholder('000.000.000-00')
            ->required()
            ->rules([new CpfRule])
            ->autofocus()
            ->extraInputAttributes([
                'tabindex' => 1,
                'aria-invalid' => ($this->hasAuthFailed || $this->getErrorBag()->has('data.cpf')) ? 'true' : 'false',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Senha')
            ->password()
            ->revealable(true)
            ->autocomplete('current-password')
            ->nullable()
            ->helperText('No primeiro acesso, deixe a senha em branco para criar sua credencial.')
            ->extraInputAttributes(['tabindex' => 2]);
    }

    protected function getRememberFormComponent(): Component
    {
        return Checkbox::make('remember')
            ->label('Manter conectado')
            ->extraInputAttributes(['tabindex' => 3]);
    }

    protected function getAuthenticateFormAction(): Action
    {
        return Action::make('authenticate')
            ->label('Entrar na Ormiban')
            ->submit('authenticate')
            ->extraAttributes([
                'tabindex' => 5,
                'class' => 'w-full min-h-[44px] text-base font-semibold text-white bg-[#E81828] hover:bg-[#c51422] focus:ring-2 focus:ring-[#E81828] focus:ring-offset-2 transition-colors duration-150 rounded-lg shadow-sm',
            ]);
    }
}
