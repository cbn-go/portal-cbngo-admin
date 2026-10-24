<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\BoardMember;
use App\Models\User;
use App\Policies\BoardMemberPolicy;
use PHPUnit\Framework\TestCase;

class BoardMemberPolicyTest extends TestCase
{
    private BoardMemberPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new BoardMemberPolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;

        return $user;
    }

    public function test_only_super_admin_can_manage_board_members(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN);
        $member = new BoardMember;

        $this->assertNull($this->policy->before($superAdmin, 'viewAny'));
        $this->assertTrue($this->policy->viewAny($superAdmin));
        $this->assertTrue($this->policy->view($superAdmin, $member));
        $this->assertTrue($this->policy->create($superAdmin));
        $this->assertTrue($this->policy->update($superAdmin, $member));
        $this->assertTrue($this->policy->reorder($superAdmin));
        $this->assertTrue($this->policy->delete($superAdmin, $member));
        $this->assertTrue($this->policy->deleteAny($superAdmin));
        $this->assertTrue($this->policy->restore($superAdmin, $member));
        $this->assertTrue($this->policy->forceDelete($superAdmin, $member));
    }

    public function test_other_roles_cannot_manage_board_members(): void
    {
        $member = new BoardMember;

        foreach ([UserRole::CONVENTION_EDITOR, UserRole::AUTHOR, UserRole::CHURCH_REPRESENTATIVE] as $i => $role) {
            $user = $this->makeUser($role, true, $i + 10);

            $this->assertFalse($this->policy->viewAny($user));
            $this->assertFalse($this->policy->view($user, $member));
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->update($user, $member));
            $this->assertFalse($this->policy->reorder($user));
            $this->assertFalse($this->policy->delete($user, $member));
            $this->assertFalse($this->policy->deleteAny($user));
        }
    }

    public function test_inactive_users_are_denied_via_before_hook(): void
    {
        $inactiveAdmin = $this->makeUser(UserRole::SUPER_ADMIN, false);

        $this->assertFalse($this->policy->before($inactiveAdmin, 'viewAny'));
        $this->assertFalse($this->policy->before($inactiveAdmin, 'update'));
    }
}
