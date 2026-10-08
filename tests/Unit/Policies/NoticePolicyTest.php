<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Notice;
use App\Models\User;
use App\Policies\NoticePolicy;
use PHPUnit\Framework\TestCase;

class NoticePolicyTest extends TestCase
{
    private NoticePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new NoticePolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;

        return $user;
    }

    private function makeNotice(): Notice
    {
        return new Notice;
    }

    public function test_super_admin_and_convention_editor_can_manage_notices(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);
        $editor = $this->makeUser(UserRole::CONVENTION_EDITOR, true, 2);
        $notice = $this->makeNotice();

        foreach ([$superAdmin, $editor] as $moderator) {
            $this->assertTrue($this->policy->viewAny($moderator));
            $this->assertTrue($this->policy->view($moderator, $notice));
            $this->assertTrue($this->policy->create($moderator));
            $this->assertTrue($this->policy->update($moderator, $notice));
            $this->assertTrue($this->policy->delete($moderator, $notice));
            $this->assertTrue($this->policy->restore($moderator, $notice));
            $this->assertTrue($this->policy->forceDelete($moderator, $notice));
        }
    }

    public function test_authors_and_church_representatives_cannot_manage_notices(): void
    {
        $author = $this->makeUser(UserRole::AUTHOR, true, 10);
        $churchRep = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, true, 20);
        $notice = $this->makeNotice();

        foreach ([$author, $churchRep] as $user) {
            $this->assertFalse($this->policy->viewAny($user));
            $this->assertFalse($this->policy->view($user, $notice));
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->update($user, $notice));
            $this->assertFalse($this->policy->delete($user, $notice));
        }
    }

    public function test_inactive_users_are_denied_via_before_hook(): void
    {
        $inactiveAdmin = $this->makeUser(UserRole::SUPER_ADMIN, false, 1);

        $this->assertFalse($this->policy->before($inactiveAdmin, 'viewAny'));
        $this->assertFalse($this->policy->before($inactiveAdmin, 'update'));
    }
}
