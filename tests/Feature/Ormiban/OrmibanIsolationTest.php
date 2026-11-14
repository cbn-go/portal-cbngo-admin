<?php

namespace Tests\Feature\Ormiban;

use App\Enums\UserRole;
use App\Models\Minister;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrmibanIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_minister_cannot_access_any_admin_panel_routes(): void
    {
        $ministerUser = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
            'cpf' => '52998224725',
        ]);

        Minister::factory()->create([
            'user_id' => $ministerUser->id,
            'cpf' => '52998224725',
        ]);

        $adminRoutes = [
            '/admin',
            '/admin/users',
            '/admin/churches',
            '/admin/ministers',
            '/admin/ormiban/indicadores',
        ];

        foreach ($adminRoutes as $route) {
            $this->actingAs($ministerUser)
                ->get($route)
                ->assertForbidden();
        }
    }

    public function test_non_authorized_roles_cannot_access_ormiban_panel(): void
    {
        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        $representative = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);

        $conventionEditor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        $ormibanRoutes = [
            '/ormiban',
            '/ormiban/profile',
            '/ormiban/minha-igreja',
        ];

        foreach ([$author, $representative, $conventionEditor] as $unauthorizedUser) {
            foreach ($ormibanRoutes as $route) {
                $this->actingAs($unauthorizedUser)
                    ->get($route)
                    ->assertForbidden();
            }
        }
    }

    public function test_inactive_minister_cannot_access_ormiban_panel(): void
    {
        $inactiveMinister = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => false,
            'must_set_password' => false,
            'cpf' => '52998224725',
        ]);

        $this->actingAs($inactiveMinister)
            ->get('/ormiban')
            ->assertForbidden();
    }

    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        $this->get('/ormiban')
            ->assertRedirect('/ormiban/login');

        $this->get('/ormiban/profile')
            ->assertRedirect('/ormiban/login');

        // And the login route itself redirects to the central admin login
        $this->get('/ormiban/login')
            ->assertRedirect('/admin/login');
    }

    public function test_super_admin_can_access_ormiban_panel_and_renders_gracefully(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->get('/ormiban')
            ->assertSuccessful();

        $this->actingAs($superAdmin)
            ->get('/ormiban/profile')
            ->assertSuccessful()
            ->assertSee('Nenhuma ficha ministerial vinculada');
    }
}
