<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\SocialProject;
use App\Models\User;
use App\Policies\SocialProjectPolicy;
use PHPUnit\Framework\TestCase;

class SocialProjectPolicyTest extends TestCase
{
    private SocialProjectPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SocialProjectPolicy;
    }

    private function makeUser(UserRole $role, ?int $churchId = null, bool $isActive = true): User
    {
        $user = new User;
        $user->id = random_int(1, 1000);
        $user->role = $role;
        $user->church_id = $churchId;
        $user->is_active = $isActive;

        return $user;
    }

    private function makeProject(int $churchId): SocialProject
    {
        $project = new SocialProject;
        $project->church_id = $churchId;

        return $project;
    }

    public function test_admins_manage_and_approve_any_project(): void
    {
        $project = $this->makeProject(7);

        foreach ([UserRole::SUPER_ADMIN, UserRole::CONVENTION_EDITOR] as $role) {
            $admin = $this->makeUser($role);

            $this->assertTrue($this->policy->viewAny($admin));
            $this->assertTrue($this->policy->view($admin, $project));
            $this->assertTrue($this->policy->create($admin));
            $this->assertTrue($this->policy->update($admin, $project));
            $this->assertTrue($this->policy->delete($admin, $project));
            $this->assertTrue($this->policy->approve($admin, $project));
            $this->assertTrue($this->policy->feature($admin, $project));
            $this->assertTrue($this->policy->forceDelete($admin, $project));
        }
    }

    public function test_representative_manages_only_own_church_projects_and_cannot_approve(): void
    {
        $representative = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, 1);
        $own = $this->makeProject(1);
        $other = $this->makeProject(2);

        $this->assertTrue($this->policy->viewAny($representative));
        $this->assertTrue($this->policy->create($representative));
        $this->assertTrue($this->policy->view($representative, $own));
        $this->assertTrue($this->policy->update($representative, $own));
        $this->assertTrue($this->policy->delete($representative, $own));

        $this->assertFalse($this->policy->view($representative, $other));
        $this->assertFalse($this->policy->update($representative, $other));
        $this->assertFalse($this->policy->delete($representative, $other));

        $this->assertFalse($this->policy->approve($representative, $own));
        $this->assertFalse($this->policy->feature($representative, $own));
        $this->assertFalse($this->policy->forceDelete($representative, $own));
    }

    public function test_representative_without_church_and_authors_are_denied(): void
    {
        $project = $this->makeProject(1);

        foreach ([$this->makeUser(UserRole::CHURCH_REPRESENTATIVE), $this->makeUser(UserRole::AUTHOR)] as $user) {
            $this->assertFalse($this->policy->viewAny($user));
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->view($user, $project));
            $this->assertFalse($this->policy->update($user, $project));
        }
    }

    public function test_inactive_users_are_denied_via_before_hook(): void
    {
        $inactive = $this->makeUser(UserRole::SUPER_ADMIN, null, false);

        $this->assertFalse($this->policy->before($inactive, 'viewAny'));
        $this->assertNull($this->policy->before($this->makeUser(UserRole::SUPER_ADMIN), 'viewAny'));
    }
}
