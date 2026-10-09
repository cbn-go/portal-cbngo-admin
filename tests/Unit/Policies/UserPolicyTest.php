<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\User;
use App\Policies\UserPolicy;
use PHPUnit\Framework\TestCase;

class UserPolicyTest extends TestCase
{
    private UserPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new UserPolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;

        return $user;
    }

    public function test_super_admin_can_manage_users(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);
        $targetUser = $this->makeUser(UserRole::AUTHOR, true, 2);

        $this->assertTrue($this->policy->viewAny($superAdmin));
        $this->assertTrue($this->policy->view($superAdmin, $targetUser));
        $this->assertTrue($this->policy->create($superAdmin));
        $this->assertTrue($this->policy->update($superAdmin, $targetUser));
        $this->assertTrue($this->policy->delete($superAdmin, $targetUser));
    }

    public function test_user_cannot_delete_themselves(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);

        $this->assertFalse($this->policy->delete($superAdmin, $superAdmin));
    }

    public function test_convention_editor_can_manage_regular_users_but_not_super_admin(): void
    {
        $editor = $this->makeUser(UserRole::CONVENTION_EDITOR, true, 2);
        $regularUser = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, true, 3);
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);

        $this->assertTrue($this->policy->viewAny($editor));
        $this->assertTrue($this->policy->create($editor));

        // Pode ver e editar usuário comum
        $this->assertTrue($this->policy->view($editor, $regularUser));
        $this->assertTrue($this->policy->update($editor, $regularUser));
        $this->assertTrue($this->policy->delete($editor, $regularUser));

        // Pode ver, mas NÃO pode alterar ou deletar SuperAdmin
        $this->assertTrue($this->policy->view($editor, $superAdmin));
        $this->assertFalse($this->policy->update($editor, $superAdmin));
        $this->assertFalse($this->policy->delete($editor, $superAdmin));
    }

    public function test_authors_and_church_representatives_cannot_manage_users(): void
    {
        $author = $this->makeUser(UserRole::AUTHOR, true, 10);
        $churchRep = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, true, 20);
        $target = $this->makeUser(UserRole::AUTHOR, true, 30);

        foreach ([$author, $churchRep] as $user) {
            $this->assertFalse($this->policy->viewAny($user));
            $this->assertFalse($this->policy->view($user, $target));
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->update($user, $target));
            $this->assertFalse($this->policy->delete($user, $target));
        }
    }

    public function test_inactive_users_are_denied_via_before_hook(): void
    {
        $inactiveAdmin = $this->makeUser(UserRole::SUPER_ADMIN, false, 1);

        $this->assertFalse($this->policy->before($inactiveAdmin, 'viewAny'));
        $this->assertFalse($this->policy->before($inactiveAdmin, 'update'));
    }

    public function test_assign_role_rules(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);
        $editor = $this->makeUser(UserRole::CONVENTION_EDITOR, true, 2);

        // Super Admin pode atribuir qualquer role
        $this->assertTrue($this->policy->assignRole($superAdmin, UserRole::SUPER_ADMIN));
        $this->assertTrue($this->policy->assignRole($superAdmin, UserRole::CONVENTION_EDITOR));
        $this->assertTrue($this->policy->assignRole($superAdmin, UserRole::AUTHOR));
        $this->assertTrue($this->policy->assignRole($superAdmin, UserRole::CHURCH_REPRESENTATIVE));

        // Editor não pode atribuir super_admin
        $this->assertFalse($this->policy->assignRole($editor, UserRole::SUPER_ADMIN));
        $this->assertTrue($this->policy->assignRole($editor, UserRole::CONVENTION_EDITOR));
        $this->assertTrue($this->policy->assignRole($editor, UserRole::AUTHOR));
        $this->assertTrue($this->policy->assignRole($editor, UserRole::CHURCH_REPRESENTATIVE));
    }
}
