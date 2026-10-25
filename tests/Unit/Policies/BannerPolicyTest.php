<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Banner;
use App\Models\User;
use App\Policies\BannerPolicy;
use PHPUnit\Framework\TestCase;

class BannerPolicyTest extends TestCase
{
    private BannerPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new BannerPolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;

        return $user;
    }

    private function makeBanner(): Banner
    {
        return new Banner;
    }

    public function test_super_admin_and_convention_editor_can_manage_banners(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);
        $editor = $this->makeUser(UserRole::CONVENTION_EDITOR, true, 2);
        $banner = $this->makeBanner();

        foreach ([$superAdmin, $editor] as $moderator) {
            $this->assertTrue($this->policy->viewAny($moderator));
            $this->assertTrue($this->policy->view($moderator, $banner));
            $this->assertTrue($this->policy->create($moderator));
            $this->assertTrue($this->policy->update($moderator, $banner));
            $this->assertTrue($this->policy->delete($moderator, $banner));
            $this->assertTrue($this->policy->restore($moderator, $banner));
            $this->assertTrue($this->policy->forceDelete($moderator, $banner));
            $this->assertTrue($this->policy->reorder($moderator));
            $this->assertTrue($this->policy->deleteAny($moderator));
        }
    }

    public function test_authors_and_church_representatives_cannot_manage_banners(): void
    {
        $author = $this->makeUser(UserRole::AUTHOR, true, 10);
        $churchRep = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, true, 20);
        $banner = $this->makeBanner();

        foreach ([$author, $churchRep] as $user) {
            $this->assertFalse($this->policy->viewAny($user));
            $this->assertFalse($this->policy->view($user, $banner));
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->update($user, $banner));
            $this->assertFalse($this->policy->delete($user, $banner));
            $this->assertFalse($this->policy->reorder($user));
            $this->assertFalse($this->policy->deleteAny($user));
        }
    }

    public function test_inactive_users_are_denied_via_before_hook(): void
    {
        $inactiveAdmin = $this->makeUser(UserRole::SUPER_ADMIN, false, 1);

        $this->assertFalse($this->policy->before($inactiveAdmin, 'viewAny'));
        $this->assertFalse($this->policy->before($inactiveAdmin, 'update'));
    }
}
