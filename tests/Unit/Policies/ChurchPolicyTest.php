<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\News;
use App\Models\User;
use App\Policies\ChurchPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ChurchPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ChurchPolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;

        return $user;
    }

    private function makeChurch(): Church
    {
        return new Church;
    }

    public function test_super_admin_and_convention_editor_can_manage_churches(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);
        $editor = $this->makeUser(UserRole::CONVENTION_EDITOR, true, 2);
        $church = $this->makeChurch();

        foreach ([$superAdmin, $editor] as $admin) {
            $this->assertTrue($this->policy->viewAny($admin));
            $this->assertTrue($this->policy->view($admin, $church));
            $this->assertTrue($this->policy->create($admin));
            $this->assertTrue($this->policy->update($admin, $church));
            $this->assertTrue($this->policy->restore($admin, $church));
            $this->assertTrue($this->policy->forceDelete($admin, $church));
        }
    }

    public function test_authors_and_church_representatives_cannot_manage_churches(): void
    {
        $author = $this->makeUser(UserRole::AUTHOR, true, 10);
        $churchRep = $this->makeUser(UserRole::CHURCH_REPRESENTATIVE, true, 20);
        $church = $this->makeChurch();

        foreach ([$author, $churchRep] as $user) {
            $this->assertFalse($this->policy->viewAny($user));
            $this->assertFalse($this->policy->view($user, $church));
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->update($user, $church));
            $this->assertFalse($this->policy->delete($user, $church));
        }
    }

    public function test_cannot_delete_church_with_associated_users_or_news(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN, true, 1);

        $churchWithUsers = Church::factory()->create();
        User::factory()->create(['church_id' => $churchWithUsers->id]);

        $churchWithNews = Church::factory()->create();
        News::factory()->create(['church_id' => $churchWithNews->id]);

        $cleanChurch = Church::factory()->create();

        $this->assertFalse($this->policy->delete($superAdmin, $churchWithUsers));
        $this->assertFalse($this->policy->delete($superAdmin, $churchWithNews));
        $this->assertTrue($this->policy->delete($superAdmin, $cleanChurch));
    }

    public function test_inactive_users_are_denied_via_before_hook(): void
    {
        $inactiveAdmin = $this->makeUser(UserRole::SUPER_ADMIN, false, 1);

        $this->assertFalse($this->policy->before($inactiveAdmin, 'viewAny'));
        $this->assertFalse($this->policy->before($inactiveAdmin, 'update'));
    }
}
