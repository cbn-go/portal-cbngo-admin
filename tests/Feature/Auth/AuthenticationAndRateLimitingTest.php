<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Auth\ResetPassword as FilamentResetPassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class AuthenticationAndRateLimitingTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_default_panel_url_matches_role_destination(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
        ]);
        $this->assertStringEndsWith('/admin', $superAdmin->getDefaultPanelUrl());

        $editor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
        ]);
        $this->assertStringEndsWith('/admin', $editor->getDefaultPanelUrl());

        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
        ]);
        $this->assertStringEndsWith('/portal', $author->getDefaultPanelUrl());

        $churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
        ]);
        $this->assertStringEndsWith('/portal', $churchRep->getDefaultPanelUrl());
    }

    public function test_login_route_redirects_unauthenticated_users_to_admin_login(): void
    {
        $this->get('/login')->assertRedirect('/admin/login');
    }

    public function test_root_route_redirects_unauthenticated_users_to_admin_login(): void
    {
        $this->get('/')->assertRedirect('/admin/login');
    }

    public function test_login_route_redirects_authenticated_users_to_their_respective_panel(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
        $this->actingAs($admin)->get('/login')->assertRedirect('/admin');

        $churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);
        $this->actingAs($churchRep)->get('/login')->assertRedirect('/portal');
    }

    public function test_root_route_redirects_authenticated_users_to_their_respective_panel(): void
    {
        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);
        $this->actingAs($author)->get('/')->assertRedirect('/portal');

        $editor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);
        $this->actingAs($editor)->get('/')->assertRedirect('/admin');
    }

    public function test_password_reset_page_is_accessible_on_both_panels(): void
    {
        $this->get('/admin/password-reset/request')->assertSuccessful();
        $this->get('/portal/password-reset/request')->assertSuccessful();
    }

    public function test_password_reset_notification_can_be_sent_for_valid_user(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email' => 'pastor@cbngo.org.br',
            'is_active' => true,
        ]);

        $status = Password::broker()->sendResetLink(['email' => $user->email]);

        $this->assertSame(Password::RESET_LINK_SENT, $status);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_inactive_user_does_not_receive_password_reset_notification(): void
    {
        Notification::fake();

        $inactiveUser = User::factory()->create([
            'email' => 'inativo@cbngo.org.br',
            'is_active' => false,
        ]);

        Password::broker()->sendResetLink(['email' => $inactiveUser->email]);

        Notification::assertNotSentTo($inactiveUser, ResetPassword::class);
    }

    public function test_minister_password_reset_from_admin_sends_ormiban_link(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $minister = User::factory()->create([
            'email' => 'ministro@cbngo.org.br',
            'role' => UserRole::MINISTER,
            'is_active' => true,
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'ministro@cbngo.org.br'])
            ->call('request');

        Notification::assertSentTo(
            $minister,
            FilamentResetPassword::class,
            fn (FilamentResetPassword $notification): bool => str_contains($notification->url, '/ormiban/password-reset/reset'),
        );
    }

    public function test_author_password_reset_from_admin_sends_portal_link(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $author = User::factory()->create([
            'email' => 'autor-reset@cbngo.org.br',
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'autor-reset@cbngo.org.br'])
            ->call('request');

        Notification::assertSentTo(
            $author,
            FilamentResetPassword::class,
            fn (FilamentResetPassword $notification): bool => str_contains($notification->url, '/portal/password-reset/reset'),
        );
    }

    public function test_admin_password_reset_keeps_admin_link(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $editor = User::factory()->create([
            'email' => 'editor-reset@cbngo.org.br',
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'editor-reset@cbngo.org.br'])
            ->call('request');

        Notification::assertSentTo(
            $editor,
            FilamentResetPassword::class,
            fn (FilamentResetPassword $notification): bool => str_contains($notification->url, '/admin/password-reset/reset'),
        );
    }

    public function test_inactive_user_password_reset_form_does_not_notify(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        User::factory()->create([
            'email' => 'inativo-reset@cbngo.org.br',
            'role' => UserRole::MINISTER,
            'is_active' => false,
        ]);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'inativo-reset@cbngo.org.br'])
            ->call('request');

        Notification::assertNothingSent();
    }

    public function test_portal_user_authenticating_via_admin_login_is_redirected_to_portal(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $author = User::factory()->create([
            'email' => 'autor@cbngo.org.br',
            'password' => 'password123',
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'identifier' => 'autor@cbngo.org.br',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertRedirect(url('/portal'));

        $this->assertAuthenticatedAs($author);
    }

    public function test_admin_user_authenticating_via_portal_login_is_redirected_to_admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));

        $editor = User::factory()->create([
            'email' => 'editor@cbngo.org.br',
            'password' => 'password123',
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'identifier' => 'editor@cbngo.org.br',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertRedirect(url('/admin'));

        $this->assertAuthenticatedAs($editor);
    }

    public function test_inactive_user_cannot_authenticate_and_receives_explicit_error(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        User::factory()->create([
            'email' => 'bloqueado@cbngo.org.br',
            'password' => 'password123',
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'identifier' => 'bloqueado@cbngo.org.br',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasErrors(['data.identifier' => 'Sua conta está desativada. Entre em contato com a administração.']);

        $this->assertGuest();
    }

    public function test_login_rate_limiting_throttles_after_multiple_failed_attempts(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(Login::class);

        for ($i = 0; $i < 5; $i++) {
            try {
                $component->fillForm([
                    'identifier' => 'wrong@email.com',
                    'password' => 'wrongpassword',
                ])->call('authenticate');
            } catch (ValidationException) {
                // Expected invalid credentials validation exception
            }
        }

        $component->fillForm([
            'identifier' => 'wrong@email.com',
            'password' => 'wrongpassword',
        ])->call('authenticate')
            ->assertNotified();
    }

    public function test_login_page_renders_custom_management_system_layout_and_elements_on_both_panels(): void
    {
        config(['portal.contact_email' => 'contato@cbngo.org.br']);
        config(['portal.site_url' => 'https://cbngo.org.br']);

        foreach (['/admin/login', '/portal/login'] as $url) {
            $response = $this->get($url);

            $response->assertSuccessful();
            $response->assertSee('Sistema de gestão da convenção');
            $response->assertSee('A administração da CBN Goiás em um só lugar.');
            $response->assertSee('Secretaria');
            $response->assertSee('Financeiro');
            $response->assertSee('Dados e relatórios');
            $response->assertSee('Portal e comunicação');
            $response->assertSee('Cada pessoa vê apenas os módulos liberados para a sua função.');
            $response->assertSee('Ambiente restrito a pessoas autorizadas pela CBN-GO.');
            $response->assertSee('Entrar no sistema');
            $response->assertSee('Use o e-mail ou o CPF e a senha cadastrados pela secretaria da CBN-GO.');
            $response->assertSee('Meu primeiro acesso');
            $response->assertSee('Voltar ao site');
            $response->assertSee('https://cbngo.org.br');
            $response->assertSee('Manter conectado');
            $response->assertSee('Esqueci minha senha');
            $response->assertSee('Após 5 tentativas sem sucesso, o acesso fica bloqueado por alguns minutos.');
            $response->assertSee('Ainda não tem acesso? Peça o cadastro à secretaria pelo e-mail');
            $response->assertSee('contato@cbngo.org.br');
        }
    }

    public function test_invalid_credentials_shows_error_alert_and_remaining_attempts(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(Login::class);

        try {
            $component->fillForm([
                'identifier' => 'wrong@email.com',
                'password' => 'wrongpassword',
            ])->call('authenticate');
        } catch (ValidationException) {
            // Expected validation failure
        }

        $component->assertHasErrors(['data.identifier'])
            ->assertSee('Não foi possível entrar. Confira o e-mail ou o CPF e a senha.')
            ->assertSee('tentativas restantes');
    }

    public function test_minister_logs_in_with_cpf_and_lands_on_ormiban(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $minister = User::factory()->create([
            'email' => 'ministro@cbngo.org.br',
            'cpf' => '52998224725',
            'password' => 'password123',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        Livewire::test(Login::class)
            ->fillForm([
                'identifier' => '529.982.247-25',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(url('/ormiban'));

        $this->assertAuthenticatedAs($minister);
    }

    public function test_first_access_with_matching_email_sets_password_and_logs_in(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $minister = User::factory()->create([
            'email' => 'ministro@cbngo.org.br',
            'cpf' => '52998224725',
            'password' => null,
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => true,
        ]);

        Livewire::test(Login::class)
            ->call('startFirstAccess')
            ->fillForm(['cpf' => '529.982.247-25'])
            ->call('continueFirstAccess')
            ->assertHasNoErrors()
            ->assertSet('step', 'email')
            ->assertSet('emailHint', 'm••••••o@cbngo.org.br')
            ->assertSee('m••••••o@cbngo.org.br')
            ->assertSee('Não reconhece este e-mail? Peça ajuda à secretaria')
            ->assertDontSee('ministro@cbngo.org.br')
            ->fillForm(['email' => 'Ministro@cbngo.org.br'])
            ->call('continueFirstAccess')
            ->assertHasNoErrors()
            ->assertSet('step', 'password')
            ->fillForm([
                'password' => 'SenhaNova123',
                'password_confirmation' => 'SenhaNova123',
            ])
            ->call('continueFirstAccess')
            ->assertHasNoErrors()
            ->assertRedirect(url('/ormiban'));

        $minister->refresh();
        $this->assertFalse($minister->must_set_password);
        $this->assertTrue(Hash::check('SenhaNova123', (string) $minister->password));
        $this->assertAuthenticatedAs($minister);
    }

    public function test_first_access_with_email_in_cpf_field_asks_for_cpf(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        User::factory()->create([
            'email' => 'reidnerpa@gmail.com',
            'cpf' => '52998224725',
            'password' => null,
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => true,
        ]);

        Livewire::test(Login::class)
            ->call('startFirstAccess')
            ->fillForm(['cpf' => 'reidnerpa@gmail.com'])
            ->call('continueFirstAccess')
            ->assertHasErrors(['data.cpf' => 'Você informou um e-mail. Neste passo, use o CPF da ficha.'])
            ->assertSet('step', 'cpf');

        $this->assertGuest();
    }

    public function test_first_access_with_unknown_cpf_mentions_only_cpf(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Login::class)
            ->call('startFirstAccess')
            ->fillForm(['cpf' => '529.982.247-25'])
            ->call('continueFirstAccess')
            ->assertHasErrors(['data.cpf' => 'O CPF informado não confere com o cadastro.'])
            ->assertSet('step', 'cpf');

        $this->assertGuest();
    }

    public function test_first_access_with_wrong_email_does_not_authenticate(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        User::factory()->create([
            'email' => 'ministro@cbngo.org.br',
            'cpf' => '52998224725',
            'password' => null,
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => true,
        ]);

        Livewire::test(Login::class)
            ->call('startFirstAccess')
            ->fillForm(['cpf' => '529.982.247-25'])
            ->call('continueFirstAccess')
            ->assertSet('emailHint', 'm••••••o@cbngo.org.br')
            ->assertSee('Não reconhece este e-mail? Peça ajuda à secretaria')
            ->fillForm(['email' => 'outro@cbngo.org.br'])
            ->call('continueFirstAccess')
            ->assertHasErrors(['data.email' => 'CPF ou e-mail não conferem com o cadastro.'])
            ->assertSet('step', 'email');

        $this->assertGuest();
    }

    public function test_first_access_without_email_asks_for_the_secretariat(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        User::factory()->create([
            'email' => null,
            'cpf' => '52998224725',
            'password' => null,
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => true,
        ]);

        Livewire::test(Login::class)
            ->call('startFirstAccess')
            ->fillForm(['cpf' => '529.982.247-25'])
            ->call('continueFirstAccess')
            ->assertHasErrors(['data.cpf' => 'Sua ficha não tem e-mail. Entre em contato com a secretaria.'])
            ->assertSet('step', 'cpf');

        $this->assertGuest();
    }

    public function test_first_access_does_not_overwrite_an_existing_password(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $minister = User::factory()->create([
            'email' => 'ministro@cbngo.org.br',
            'cpf' => '52998224725',
            'password' => 'password123',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        Livewire::test(Login::class)
            ->call('startFirstAccess')
            ->fillForm(['cpf' => '529.982.247-25'])
            ->call('continueFirstAccess')
            ->assertHasErrors(['data.cpf' => 'Esta conta já tem senha. Use o login normal.'])
            ->assertSet('step', 'cpf');

        $minister->refresh();
        $this->assertTrue(Hash::check('password123', (string) $minister->password));
        $this->assertGuest();
    }
}
