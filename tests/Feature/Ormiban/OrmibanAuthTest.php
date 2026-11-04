<?php

namespace Tests\Feature\Ormiban;

use App\Enums\UserRole;
use App\Filament\Ormiban\Pages\Auth\OrmibanLogin;
use App\Filament\Ormiban\Pages\Auth\SetInitialPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OrmibanAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get('/ormiban/login');

        $response->assertSuccessful();
        $response->assertSee('CPF');
    }

    public function test_login_with_invalid_cpf_checksum_fails_validation(): void
    {
        Livewire::test(OrmibanLogin::class)
            ->set('data.cpf', '111.444.777-30') // Invalid check digits
            ->set('data.password', 'QualquerSenha123!')
            ->call('authenticate')
            ->assertHasErrors(['data.cpf']);
    }

    public function test_login_with_unregistered_cpf_fails_authentication(): void
    {
        Livewire::test(OrmibanLogin::class)
            ->set('data.cpf', '529.982.247-25') // Valid algorithm, but not in DB
            ->set('data.password', 'SenhaValida123!')
            ->call('authenticate')
            ->assertHasErrors(['data.cpf']);
    }

    public function test_login_with_inactive_user_is_blocked(): void
    {
        User::factory()->create([
            'cpf' => '52998224725',
            'role' => UserRole::MINISTER,
            'is_active' => false,
            'password' => Hash::make('SenhaForte123!'),
        ]);

        Livewire::test(OrmibanLogin::class)
            ->set('data.cpf', '529.982.247-25')
            ->set('data.password', 'SenhaForte123!')
            ->call('authenticate')
            ->assertHasErrors(['data.cpf']);

        $this->assertGuest();
    }

    public function test_login_rate_limiting_blocks_after_five_attempts(): void
    {
        $component = Livewire::test(OrmibanLogin::class);

        for ($i = 0; $i < 5; $i++) {
            $component->set('data.cpf', '529.982.247-25')
                ->set('data.password', 'SenhaErrada'.$i)
                ->call('authenticate');
        }

        // 6th attempt should be blocked by rate limit
        $component->set('data.cpf', '529.982.247-25')
            ->set('data.password', 'SenhaErrada6')
            ->call('authenticate')
            ->assertNotified();
    }

    public function test_minister_with_valid_password_logs_in_successfully(): void
    {
        $user = User::factory()->create([
            'cpf' => '52998224725',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
            'password' => Hash::make('SenhaCorreta123!'),
        ]);

        Livewire::test(OrmibanLogin::class)
            ->set('data.cpf', '529.982.247-25')
            ->set('data.password', 'SenhaCorreta123!')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/ormiban');

        $this->assertAuthenticatedAs($user);
    }

    public function test_minister_on_first_access_is_forced_to_set_password(): void
    {
        $user = User::factory()->create([
            'cpf' => '52998224725',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => true,
            'password' => null,
        ]);

        // In first access, CPF alone or with blank password can be submitted
        Livewire::test(OrmibanLogin::class)
            ->set('data.cpf', '529.982.247-25')
            ->set('data.password', '')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect('/ormiban/definir-senha');

        $this->assertAuthenticatedAs($user);

        // Accessing /ormiban directly while must_set_password is true should redirect to /ormiban/definir-senha
        $response = $this->actingAs($user)->get('/ormiban');
        $response->assertRedirect('/ormiban/definir-senha');
    }

    public function test_minister_setting_strong_password_updates_user_and_grants_access(): void
    {
        $user = User::factory()->create([
            'cpf' => '52998224725',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => true,
            'password' => null,
        ]);

        // Attempting with weak or unconfirmed password
        Livewire::actingAs($user)
            ->test(SetInitialPassword::class)
            ->set('data.password', '123')
            ->set('data.password_confirmation', '456')
            ->call('save')
            ->assertHasErrors(['data.password']);

        // Submitting a strong confirmed password
        Livewire::actingAs($user)
            ->test(SetInitialPassword::class)
            ->set('data.password', 'NovaSenhaForte@2026')
            ->set('data.password_confirmation', 'NovaSenhaForte@2026')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect('/ormiban');

        $user->refresh();
        $this->assertFalse($user->must_set_password);
        $this->assertTrue(Hash::check('NovaSenhaForte@2026', (string) $user->password));

        // Now direct access to /ormiban is granted
        $response = $this->actingAs($user)->get('/ormiban');
        $response->assertSuccessful();
    }
}
