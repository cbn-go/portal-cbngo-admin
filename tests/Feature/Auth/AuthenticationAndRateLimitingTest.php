<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Filament\Pages\Auth\Login;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
                'email' => 'autor@cbngo.org.br',
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
                'email' => 'editor@cbngo.org.br',
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
                'email' => 'bloqueado@cbngo.org.br',
                'password' => 'password123',
            ])
            ->call('authenticate')
            ->assertHasErrors(['data.email' => 'Sua conta está desativada. Entre em contato com a administração.']);

        $this->assertGuest();
    }

    public function test_login_rate_limiting_throttles_after_multiple_failed_attempts(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $component = Livewire::test(Login::class);

        for ($i = 0; $i < 5; $i++) {
            try {
                $component->fillForm([
                    'email' => 'wrong@email.com',
                    'password' => 'wrongpassword',
                ])->call('authenticate');
            } catch (ValidationException) {
                // Expected invalid credentials validation exception
            }
        }

        $component->fillForm([
            'email' => 'wrong@email.com',
            'password' => 'wrongpassword',
        ])->call('authenticate')
            ->assertNotified();
    }
}
