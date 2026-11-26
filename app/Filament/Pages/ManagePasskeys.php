<?php

namespace App\Filament\Pages;

use App\Models\Passkey;
use App\Models\User;
use App\Services\WebAuthn\Exceptions\WebAuthnCeremonyException;
use App\Services\WebAuthn\WebAuthnRegistrationService;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Throwable;

class ManagePasskeys extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-finger-print';

    protected static ?string $slug = 'passkeys';

    protected static ?string $navigationGroup = 'Segurança';

    protected static ?string $navigationLabel = 'Minhas passkeys';

    protected static ?string $title = 'Minhas passkeys';

    protected static string $view = 'filament.juban.pages.manage-passkeys';

    public string $name = '';

    public string $password = '';

    public ?int $editingPasskeyId = null;

    public string $editingPasskeyName = '';

    public static function canAccess(): bool
    {
        /** @var User|null $user */
        $user = auth()->user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        $panel = Filament::getCurrentPanel();
        $panelId = $panel?->getId();

        if ($panelId === 'juban') {
            return $user->role->canViewJubanTreasury();
        }

        if ($panelId === 'conselho-fiscal') {
            return $user->role->isFiscalCouncil() || $user->role->isSuperAdmin();
        }

        return false;
    }

    /**
     * @return Collection<int, Passkey>
     */
    public function getPasskeysProperty(): Collection
    {
        /** @var User $user */
        $user = auth()->user();

        return $user->passkeys()->latest()->get();
    }

    public function startRegistration(string $name, string $password): void
    {
        /** @var User $user */
        $user = auth()->user();

        if (! Hash::check($password, (string) $user->password)) {
            $this->addError('password', 'A senha atual está incorreta.');

            return;
        }

        $service = app(WebAuthnRegistrationService::class);
        $options = $service->generateCreationOptions($user);

        $this->name = $name;
        $this->password = '';

        $this->dispatch('close-modal', id: 'register-passkey-modal');

        // Argumentos nomeados: no navegador viram event.detail.options e event.detail.name.
        $this->dispatch('init-webauthn-registration', options: $options, name: $name);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function finishRegistration(string $name, array $payload): void
    {
        /** @var User $user */
        $user = auth()->user();

        $service = app(WebAuthnRegistrationService::class);

        try {
            $service->verifyAndSave($user, $name, $payload);

            Notification::make()
                ->title('Passkey cadastrada com sucesso!')
                ->success()
                ->send();

            $this->name = '';
            $this->password = '';
        } catch (WebAuthnCeremonyException $e) {
            Notification::make()
                ->title('Falha ao cadastrar passkey')
                ->body($e->getMessage())
                ->danger()
                ->send();
        } catch (Throwable) {
            Notification::make()
                ->title('Erro inesperado')
                ->body('Ocorreu um erro ao processar a credencial.')
                ->danger()
                ->send();
        }
    }

    public function handleRegistrationError(string $error): void
    {
        Notification::make()
            ->title('Falha ao cadastrar passkey')
            ->body($error)
            ->danger()
            ->send();
    }

    public function renamePasskey(int $id, string $newName): void
    {
        /** @var User $user */
        $user = auth()->user();

        $passkey = Passkey::find($id);

        if (! $passkey || $passkey->user_id !== $user->id) {
            abort(403);
        }

        $trimmed = trim($newName);
        if ($trimmed === '') {
            $this->addError('editingPasskeyName', 'O nome da passkey não pode ficar em branco.');

            return;
        }

        $passkey->update(['name' => $trimmed]);

        $this->editingPasskeyId = null;
        $this->editingPasskeyName = '';

        Notification::make()
            ->title('Passkey renomeada com sucesso!')
            ->success()
            ->send();
    }

    public function deletePasskey(int $id): void
    {
        /** @var User $user */
        $user = auth()->user();

        $passkey = Passkey::find($id);

        if (! $passkey || $passkey->user_id !== $user->id) {
            abort(403);
        }

        $passkey->delete();

        Notification::make()
            ->title('Passkey removida com sucesso.')
            ->success()
            ->send();
    }
}
