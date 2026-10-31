<?php

namespace Tests\Feature\Filament\Strategy;

use App\Enums\UserRole;
use App\Models\Municipality;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StrategyAccessTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function urls(Municipality $municipality): array
    {
        return [
            '/admin/estrategia',
            '/admin/estrategia/municipios',
            "/admin/estrategia/municipios/{$municipality->id}",
            '/admin/estrategia/metodologia',
        ];
    }

    public function test_super_admin_and_convention_editor_can_open_every_strategy_screen(): void
    {
        $municipality = Municipality::factory()->create();

        foreach ([UserRole::SUPER_ADMIN, UserRole::CONVENTION_EDITOR] as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);

            foreach ($this->urls($municipality) as $url) {
                $this->actingAs($user)->get($url)->assertSuccessful();
            }
        }
    }

    public function test_other_roles_and_inactive_users_are_forbidden(): void
    {
        $municipality = Municipality::factory()->create();

        $users = [
            User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]),
            User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => false]),
        ];

        foreach ($users as $user) {
            foreach ($this->urls($municipality) as $url) {
                $this->actingAs($user)->get($url)->assertForbidden();
            }
        }
    }

    public function test_strategy_screens_do_not_exist_in_the_portal_panel(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);

        foreach (['/portal/estrategia', '/portal/estrategia/municipios', '/portal/estrategia/metodologia'] as $url) {
            $this->actingAs($user)->get($url)->assertNotFound();
        }
    }

    public function test_municipalities_are_read_only(): void
    {
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $municipality = Municipality::factory()->create();

        $this->assertFalse($user->can('create', Municipality::class));
        $this->assertFalse($user->can('update', $municipality));
        $this->assertFalse($user->can('delete', $municipality));
    }
}
