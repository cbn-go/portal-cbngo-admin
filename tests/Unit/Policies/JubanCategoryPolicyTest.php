<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\JubanCategory;
use App\Models\User;
use App\Policies\JubanCategoryPolicy;
use Tests\TestCase;

class JubanCategoryPolicyTest extends TestCase
{
    private JubanCategoryPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new JubanCategoryPolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;

        return $user;
    }

    private function makeCategory(int $id = 1): JubanCategory
    {
        $category = new JubanCategory;
        $category->id = $id;

        return $category;
    }

    public function test_super_admin_has_full_access_to_categories(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN);
        $category = $this->makeCategory();

        $this->assertTrue($this->policy->viewAny($superAdmin));
        $this->assertTrue($this->policy->view($superAdmin, $category));
        $this->assertTrue($this->policy->create($superAdmin));
        $this->assertTrue($this->policy->update($superAdmin, $category));
        $this->assertTrue($this->policy->delete($superAdmin, $category));
    }

    public function test_juban_treasurer_can_manage_categories(): void
    {
        $treasurer = $this->makeUser(UserRole::JUBAN_TREASURER);
        $category = $this->makeCategory();

        $this->assertTrue($this->policy->viewAny($treasurer));
        $this->assertTrue($this->policy->view($treasurer, $category));
        $this->assertTrue($this->policy->create($treasurer));
        $this->assertTrue($this->policy->update($treasurer, $category));
        $this->assertTrue($this->policy->delete($treasurer, $category));
    }

    public function test_convention_editor_secretary_can_view_and_manage_categories_but_cannot_delete(): void
    {
        $secretary = $this->makeUser(UserRole::CONVENTION_EDITOR);
        $category = $this->makeCategory();

        $this->assertTrue($this->policy->viewAny($secretary));
        $this->assertTrue($this->policy->view($secretary, $category));
        $this->assertTrue($this->policy->create($secretary));
        $this->assertTrue($this->policy->update($secretary, $category));

        // Strict mandate: Secretary cannot delete categories under any circumstance
        $this->assertFalse($this->policy->delete($secretary, $category));
    }

    public function test_other_roles_have_no_access_to_categories(): void
    {
        $roles = [
            UserRole::MINISTER,
            UserRole::AUTHOR,
            UserRole::CHURCH_REPRESENTATIVE,
        ];

        $category = $this->makeCategory();

        foreach ($roles as $role) {
            $user = $this->makeUser($role);

            $this->assertFalse($this->policy->viewAny($user));
            $this->assertFalse($this->policy->view($user, $category));
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->update($user, $category));
            $this->assertFalse($this->policy->delete($user, $category));
        }
    }

    public function test_inactive_users_are_strictly_forbidden_via_before_hook(): void
    {
        $inactiveSuperAdmin = $this->makeUser(UserRole::SUPER_ADMIN, false);
        $inactiveTreasurer = $this->makeUser(UserRole::JUBAN_TREASURER, false);
        $inactiveSecretary = $this->makeUser(UserRole::CONVENTION_EDITOR, false);

        foreach ([$inactiveSuperAdmin, $inactiveTreasurer, $inactiveSecretary] as $inactiveUser) {
            $this->assertFalse($this->policy->before($inactiveUser, 'viewAny'));
            $this->assertFalse($this->policy->before($inactiveUser, 'create'));
            $this->assertFalse($this->policy->before($inactiveUser, 'delete'));
        }
    }
}
