<?php

namespace Tests\Unit\Models;

use App\Enums\UserRole;
use App\Models\User;
use Filament\Panel;
use Tests\TestCase;

class UserRoleMinisterTest extends TestCase
{
    public function test_user_role_minister_case_and_label_exist(): void
    {
        $role = UserRole::from('minister');
        $this->assertSame(UserRole::MINISTER, $role);
        $this->assertSame('Ministro Ormiban', $role->getLabel());
        $this->assertTrue($role->isMinister());
    }

    public function test_minister_has_exclusive_panel_access(): void
    {
        $ministerRole = UserRole::MINISTER;

        $this->assertTrue($ministerRole->hasOrmibanPanelAccess());
        $this->assertFalse($ministerRole->hasAdminPanelAccess());
        $this->assertFalse($ministerRole->hasPortalPanelAccess());

        $superAdminRole = UserRole::SUPER_ADMIN;
        $this->assertTrue($superAdminRole->hasOrmibanPanelAccess());
        $this->assertTrue($superAdminRole->hasAdminPanelAccess());
        $this->assertTrue($superAdminRole->hasPortalPanelAccess());

        $editorRole = UserRole::CONVENTION_EDITOR;
        $this->assertFalse($editorRole->hasOrmibanPanelAccess());
        $this->assertTrue($editorRole->hasAdminPanelAccess());

        $authorRole = UserRole::AUTHOR;
        $this->assertFalse($authorRole->hasOrmibanPanelAccess());
        $this->assertTrue($authorRole->hasPortalPanelAccess());

        $churchRole = UserRole::CHURCH_REPRESENTATIVE;
        $this->assertFalse($churchRole->hasOrmibanPanelAccess());
        $this->assertTrue($churchRole->hasPortalPanelAccess());
    }

    public function test_user_can_access_panel_for_ormiban(): void
    {
        $adminPanel = (new Panel)->id('admin');
        $portalPanel = (new Panel)->id('portal');
        $ormibanPanel = (new Panel)->id('ormiban');

        $minister = new User([
            'name' => 'Pastor Silva',
            'email' => 'silva@example.com',
            'cpf' => '52998224725',
            'role' => UserRole::MINISTER,
            'is_active' => true,
        ]);

        $this->assertTrue($minister->canAccessPanel($ormibanPanel));
        $this->assertFalse($minister->canAccessPanel($adminPanel));
        $this->assertFalse($minister->canAccessPanel($portalPanel));

        $inactiveMinister = new User([
            'name' => 'Pastor Inativo',
            'role' => UserRole::MINISTER,
            'is_active' => false,
        ]);

        $this->assertFalse($inactiveMinister->canAccessPanel($ormibanPanel));
    }
}
