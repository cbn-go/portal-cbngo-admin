<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.layouts.auth';

    protected static string $view = 'filament.pages.auth.login';

    public ?int $attemptsRemaining = null;

    public bool $hasAuthFailed = false;

    public function mount(): void
    {
        parent::mount();

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

        if (! Filament::auth()->attempt($this->getCredentialsFromFormData($data), $data['remember'] ?? false)) {
            $key = $this->getRateLimitKey('authenticate', static::class);
            $this->attemptsRemaining = max(0, RateLimiter::remaining($key, 5));
            $this->hasAuthFailed = true;

            $this->throwFailureValidationException();
        }

        /** @var User|null $user */
        $user = Filament::auth()->user();

        if ($user === null) {
            $this->throwFailureValidationException();
        }

        if (! $user->is_active) {
            Filament::auth()->logout();

            throw ValidationException::withMessages([
                'data.email' => __('Sua conta está desativada. Entre em contato com a administração.'),
            ]);
        }

        $this->clearRateLimiter('authenticate', static::class);

        session()->regenerate();

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

        return app(LoginResponse::class);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('email')
            ->label('E-mail')
            ->email()
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes([
                'tabindex' => 1,
                'aria-invalid' => ($this->hasAuthFailed || $this->getErrorBag()->has('data.email')) ? 'true' : 'false',
            ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return TextInput::make('password')
            ->label('Senha')
            ->password()
            ->revealable(true)
            ->autocomplete('current-password')
            ->required()
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
            ->label('Entrar')
            ->submit('authenticate')
            ->extraAttributes([
                'tabindex' => 5,
                'class' => 'w-full min-h-[44px] text-base font-semibold text-white bg-[#E81828] hover:bg-[#c51422] focus:ring-2 focus:ring-[#E81828] focus:ring-offset-2 transition-colors duration-150 rounded-lg shadow-sm',
            ]);
    }
}
