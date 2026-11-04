<?php

namespace App\Filament\Ormiban\Pages\Auth;

use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * @property Form $form
 */
class SetInitialPassword extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string $layout = 'filament.layouts.auth';

    protected static string $view = 'filament.ormiban.pages.auth.set-initial-password';

    protected static ?string $slug = 'definir-senha';

    protected static ?string $title = 'Definir Senha de Primeiro Acesso';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('password')
                    ->label('Nova Senha')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rules(['confirmed', Password::min(8)])
                    ->helperText('Mínimo de 8 caracteres.'),
                TextInput::make('password_confirmation')
                    ->label('Confirmar Nova Senha')
                    ->password()
                    ->revealable()
                    ->required(),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        /** @var User|null $user */
        $user = auth()->user();

        if ($user === null) {
            $this->redirect('/ormiban/login');

            return;
        }

        $user->forceFill([
            'password' => Hash::make((string) $state['password']),
            'must_set_password' => false,
        ])->save();

        Notification::make()
            ->title('Senha definida com sucesso!')
            ->body('Seu acesso ao módulo Ormiban foi liberado.')
            ->success()
            ->send();

        $this->redirect('/ormiban');
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Salvar Senha e Acessar')
                ->submit('save')
                ->extraAttributes([
                    'class' => 'w-full min-h-[44px] text-base font-semibold text-white bg-[#E81828] hover:bg-[#c51422] rounded-lg shadow-sm',
                ]),
        ];
    }
}
