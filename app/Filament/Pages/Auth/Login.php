<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Facades\Filament;
use Filament\Http\Responses\Auth\Contracts\LoginResponse;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Validation\ValidationException;

class Login extends BaseLogin
{
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
}
