<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_access_panel_method_respects_roles_and_active_status(): void
    {
        $adminPanel = Filament::getPanel('admin');
        $portalPanel = Filament::getPanel('portal');

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
        $this->assertTrue($superAdmin->canAccessPanel($adminPanel));
        $this->assertTrue($superAdmin->canAccessPanel($portalPanel));

        $editor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);
        $this->assertTrue($editor->canAccessPanel($adminPanel));
        $this->assertFalse($editor->canAccessPanel($portalPanel));

        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);
        $this->assertFalse($author->canAccessPanel($adminPanel));
        $this->assertTrue($author->canAccessPanel($portalPanel));

        $churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);
        $this->assertFalse($churchRep->canAccessPanel($adminPanel));
        $this->assertTrue($churchRep->canAccessPanel($portalPanel));

        $inactiveSuperAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
        ]);
        $this->assertFalse($inactiveSuperAdmin->canAccessPanel($adminPanel));
        $this->assertFalse($inactiveSuperAdmin->canAccessPanel($portalPanel));
    }

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->get('/portal')->assertRedirect('/portal/login');
    }

    public function test_super_admin_can_access_both_admin_and_portal_panels(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)->get('/admin')->assertSuccessful();
        $this->actingAs($superAdmin)->get('/portal')->assertSuccessful();
    }

    public function test_convention_editor_can_access_admin_panel_but_forbidden_on_portal(): void
    {
        $editor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        $this->actingAs($editor)->get('/admin')->assertSuccessful();
        $this->actingAs($editor)->get('/portal')->assertForbidden();
    }

    public function test_author_can_access_portal_panel_but_forbidden_on_admin(): void
    {
        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        $this->actingAs($author)->get('/portal')->assertSuccessful();
        $this->actingAs($author)->get('/admin')->assertForbidden();
    }

    public function test_church_representative_can_access_portal_panel_but_forbidden_on_admin(): void
    {
        $churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);

        $this->actingAs($churchRep)->get('/portal')->assertSuccessful();
        $this->actingAs($churchRep)->get('/admin')->assertForbidden();
    }

    public function test_inactive_users_are_forbidden_from_all_panels(): void
    {
        $inactiveUser = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($inactiveUser)->get('/admin')->assertForbidden();
        $this->actingAs($inactiveUser)->get('/portal')->assertForbidden();
    }
}
