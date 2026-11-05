<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
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
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
    protected static string $layout = 'filament.layouts.auth';

    protected static string $view = 'filament.pages.auth.login';

    public ?int $attemptsRemaining = null;

    public bool $hasAuthFailed = false;

    public string $step = 'login';

    public ?string $firstAccessCpf = null;

    public ?string $firstAccessEmail = null;

    public ?string $emailHint = null;

    public function mount(): void
    {
        parent::mount();

        $this->data['remember'] = false;

        $key = $this->getRateLimitKey('authenticate', static::class);
        $this->attemptsRemaining = max(0, RateLimiter::remaining($key, 5));
    }

    public function authenticate(): ?LoginResponse
    {
        if (! $this->guardRateLimit()) {
            return null;
        }

        $data = $this->form->getState();
        $remember = (bool) ($this->data['remember'] ?? false);
        $user = $this->userFromIdentifier((string) ($data['identifier'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($user === null || $user->password === null || ! Hash::check($password, $user->password)) {
            $this->registerFailedAttempt();
            $this->throwFailureValidationException();
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages([
                'data.identifier' => __('Sua conta está desativada. Entre em contato com a administração.'),
            ]);
        }

        return $this->loginAndRedirect($user, $remember);
    }

    public function startFirstAccess(): void
    {
        $this->step = 'cpf';
        $this->hasAuthFailed = false;
        $this->firstAccessCpf = null;
        $this->firstAccessEmail = null;
        $this->emailHint = null;
        $this->refreshFormSchema();
    }

    public function backToLogin(): void
    {
        $this->step = 'login';
        $this->hasAuthFailed = false;
        $this->firstAccessCpf = null;
        $this->firstAccessEmail = null;
        $this->emailHint = null;
        $this->refreshFormSchema();
    }

    public function continueFirstAccess(): ?LoginResponse
    {
        if (! $this->guardRateLimit()) {
            return null;
        }

        $data = $this->form->getState();

        return match ($this->step) {
            'cpf' => $this->confirmCpf((string) ($data['cpf'] ?? '')),
            'email' => $this->confirmEmail((string) ($data['email'] ?? '')),
            'password' => $this->definePassword(
                (string) ($data['password'] ?? ''),
            ),
            default => null,
        };
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema(match ($this->step) {
                'cpf' => [
                    TextInput::make('cpf')
                        ->label('CPF')
                        ->required()
                        ->autofocus(),
                ],
                'email' => [
                    TextInput::make('email')
                        ->label('E-mail do cadastro')
                        ->email()
                        ->required()
                        ->autofocus(),
                ],
                'password' => [
                    TextInput::make('password')
                        ->label('Senha')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(Password::min(8))
                        ->confirmed()
                        ->helperText('Mínimo de 8 caracteres.')
                        ->autofocus(),
                    TextInput::make('password_confirmation')
                        ->label('Confirmar senha')
                        ->password()
                        ->revealable()
                        ->required(),
                ],
                default => [
                    $this->getIdentifierFormComponent(),
                    $this->getPasswordFormComponent(),
                ],
            })
            ->statePath('data');
    }

    protected function getIdentifierFormComponent(): Component
    {
        return TextInput::make('identifier')
            ->label('E-mail ou CPF')
            ->required()
            ->autocomplete('username')
            ->autofocus()
            ->extraInputAttributes([
                'tabindex' => 1,
                'aria-invalid' => ($this->hasAuthFailed || $this->getErrorBag()->has('data.identifier')) ? 'true' : 'false',
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

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        if ($this->step === 'login') {
            return [
                $this->getAuthenticateFormAction(),
            ];
        }

        return [
            Action::make('continueFirstAccess')
                ->label($this->step === 'password' ? 'Salvar senha e acessar' : 'Continuar')
                ->submit('continueFirstAccess')
                ->extraAttributes([
                    'class' => 'w-full min-h-[44px] text-base font-semibold text-white bg-[#E81828] hover:bg-[#c51422] focus:ring-2 focus:ring-[#E81828] focus:ring-offset-2 transition-colors duration-150 rounded-lg shadow-sm',
                ]),
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.identifier' => __('As credenciais informadas não coincidem com nossos registros.'),
        ]);
    }

    private function confirmCpf(string $cpf): null
    {
        if (str_contains($cpf, '@')) {
            $this->registerFailedAttempt();
            $this->failFirstAccess('data.cpf', 'Você informou um e-mail. Neste passo, use o CPF da ficha.');
        }

        $digits = Cpf::normalize($cpf);
        $user = ($digits !== null && Cpf::isValid($digits))
            ? User::query()->where('cpf', $digits)->first()
            : null;

        if ($user === null) {
            $this->registerFailedAttempt();
            $this->failFirstAccess('data.cpf', 'O CPF informado não confere com o cadastro.');
        }

        if (! $user->is_active) {
            $this->failFirstAccess('data.cpf', 'Sua conta está desativada. Entre em contato com a administração.');
        }

        if ($user->password !== null && ! $user->must_set_password) {
            $this->failFirstAccess('data.cpf', 'Esta conta já tem senha. Use o login normal.');
        }

        if (blank($user->email)) {
            $this->failFirstAccess('data.cpf', 'Sua ficha não tem e-mail. Entre em contato com a secretaria.');
        }

        $this->firstAccessCpf = $digits;
        $this->emailHint = $this->maskEmail((string) $user->email);
        $this->step = 'email';
        $this->refreshAttemptsRemaining();
        $this->refreshFormSchema();

        return null;
    }

    private function confirmEmail(string $email): null
    {
        $user = $this->firstAccessUser();

        if ($user === null || ! $this->emailMatches($user, $email)) {
            $this->registerFailedAttempt();
            $this->failFirstAccess('data.email', 'CPF ou e-mail não conferem com o cadastro.');
        }

        $this->firstAccessEmail = mb_strtolower(trim($email));
        $this->step = 'password';
        $this->refreshAttemptsRemaining();
        $this->refreshFormSchema();

        return null;
    }

    private function definePassword(string $password): LoginResponse
    {
        $user = $this->firstAccessUser();

        if ($user === null || ! $this->emailMatches($user, (string) $this->firstAccessEmail)) {
            $this->registerFailedAttempt();
            $this->failFirstAccess('data.password', 'CPF ou e-mail não conferem com o cadastro.');
        }

        if (! $user->is_active) {
            $this->failFirstAccess('data.password', 'Sua conta está desativada. Entre em contato com a administração.');
        }

        if ($user->password !== null && ! $user->must_set_password) {
            $this->failFirstAccess('data.password', 'Esta conta já tem senha. Use o login normal.');
        }

        $user->forceFill([
            'password' => $password,
            'must_set_password' => false,
        ])->save();

        return $this->loginAndRedirect($user, false);
    }

    private function loginAndRedirect(User $user, bool $remember): LoginResponse
    {
        Filament::auth()->login($user, $remember);
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

    private function userFromIdentifier(string $identifier): ?User
    {
        $identifier = trim($identifier);

        if ($identifier === '') {
            return null;
        }

        if (str_contains($identifier, '@')) {
            return User::query()
                ->whereRaw('LOWER(email) = ?', [mb_strtolower($identifier)])
                ->first();
        }

        $digits = Cpf::normalize($identifier);

        if ($digits === null || ! Cpf::isValid($digits)) {
            return null;
        }

        return User::query()->where('cpf', $digits)->first();
    }

    private function firstAccessUser(): ?User
    {
        if ($this->firstAccessCpf === null) {
            return null;
        }

        return User::query()->where('cpf', $this->firstAccessCpf)->first();
    }

    private function maskEmail(string $email): string
    {
        $email = mb_strtolower(trim($email));
        $at = mb_strrpos($email, '@');

        if ($at === false || $at === 0) {
            return $email;
        }

        $local = mb_substr($email, 0, $at);
        $domain = mb_substr($email, $at + 1);
        $length = mb_strlen($local);

        if ($length <= 1) {
            $maskedLocal = $local;
        } else {
            $maskedLocal = mb_substr($local, 0, 1)
                .str_repeat('•', $length - 2)
                .mb_substr($local, -1);
        }

        return $maskedLocal.'@'.$domain;
    }

    private function emailMatches(User $user, string $email): bool
    {
        return mb_strtolower(trim((string) $user->email)) === mb_strtolower(trim($email));
    }

    private function guardRateLimit(): bool
    {
        try {
            $this->rateLimit(5);
        } catch (TooManyRequestsException $exception) {
            $this->getRateLimitedNotification($exception)?->send();

            return false;
        }

        return true;
    }

    private function registerFailedAttempt(): void
    {
        $key = $this->getRateLimitKey('authenticate', static::class);
        $this->attemptsRemaining = max(0, RateLimiter::remaining($key, 5));
        $this->hasAuthFailed = true;
    }

    private function refreshAttemptsRemaining(): void
    {
        $key = $this->getRateLimitKey('authenticate', static::class);
        $this->attemptsRemaining = max(0, RateLimiter::remaining($key, 5));
        $this->hasAuthFailed = false;
    }

    private function failFirstAccess(string $field, string $message): never
    {
        throw ValidationException::withMessages([
            $field => $message,
        ]);
    }

    private function refreshFormSchema(): void
    {
        $this->hasCachedForms = false;
        $this->cachedForms = null;
        $this->cachedFormActions = [];
        $this->form->fill();
        $this->cacheFormActions();
    }
}
