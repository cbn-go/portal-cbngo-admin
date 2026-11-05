<?php

namespace Tests\Feature\Ormiban;

use App\Enums\UserRole;
use App\Filament\Ormiban\Pages\Auth\SetInitialPassword;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class OrmibanAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_ormiban_login_redirects_to_the_admin_login(): void
    {
        $this->get('/ormiban/login')->assertRedirect('/admin/login');
    }

    public function test_authenticated_minister_without_password_is_sent_to_set_password(): void
    {
        $user = User::factory()->create([
            'cpf' => '52998224725',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => true,
            'password' => null,
        ]);

        $this->actingAs($user)
            ->get('/ormiban')
            ->assertRedirect('/ormiban/definir-senha');
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
