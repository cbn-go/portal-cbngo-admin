<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FiscalCouncilPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_fiscal_council_user_can_access_fiscal_council_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/conselho-fiscal')
            ->assertSuccessful();
    }

    public function test_super_admin_can_access_fiscal_council_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/conselho-fiscal')
            ->assertSuccessful();
    }

    public function test_inactive_fiscal_council_user_is_forbidden(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => false,
        ]);

        $this->actingAs($user)
            ->get('/conselho-fiscal')
            ->assertForbidden();
    }

    #[DataProvider('otherRolesProvider')]
    public function test_other_roles_cannot_access_fiscal_council_panel(UserRole $role): void
    {
        $user = User::factory()->create([
            'role' => $role,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/conselho-fiscal')
            ->assertForbidden();
    }

    public function test_fiscal_council_user_cannot_access_juban_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/juban')
            ->assertForbidden();
    }

    public function test_fiscal_council_user_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_fiscal_council_user_cannot_access_portal_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/portal')
            ->assertForbidden();
    }

    public function test_fiscal_council_user_cannot_access_ormiban_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->get('/ormiban')
            ->assertForbidden();
    }

    public function test_fiscal_council_user_default_panel_url_is_conselho_fiscal(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => true,
        ]);

        $this->assertSame(url('/conselho-fiscal'), $user->getDefaultPanelUrl());
    }

    /**
     * @return array<string, array{0: UserRole}>
     */
    public static function otherRolesProvider(): array
    {
        return [
            'editor da convencao' => [UserRole::CONVENTION_EDITOR],
            'autor' => [UserRole::AUTHOR],
            'representante de igreja' => [UserRole::CHURCH_REPRESENTATIVE],
            'ministro ormiban' => [UserRole::MINISTER],
            'tesoureiro juban' => [UserRole::JUBAN_TREASURER],
            'presidente juban' => [UserRole::JUBAN_PRESIDENT],
        ];
    }
}
