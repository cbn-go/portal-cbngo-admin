<?php

namespace Tests\Unit\Policies;

use App\Enums\UserRole;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Policies\JubanTransactionPolicy;
use PHPUnit\Framework\TestCase;

class JubanTransactionPolicyTest extends TestCase
{
    private JubanTransactionPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new JubanTransactionPolicy;
    }

    private function makeUser(UserRole $role, bool $isActive = true, int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->role = $role;
        $user->is_active = $isActive;

        return $user;
    }

    private function makeTransaction(int $id = 1): JubanTransaction
    {
        $transaction = new JubanTransaction;
        $transaction->id = $id;

        return $transaction;
    }

    public function test_super_admin_has_full_access_to_transactions(): void
    {
        $superAdmin = $this->makeUser(UserRole::SUPER_ADMIN);
        $transaction = $this->makeTransaction();

        $this->assertTrue($this->policy->viewAny($superAdmin));
        $this->assertTrue($this->policy->view($superAdmin, $transaction));
        $this->assertTrue($this->policy->create($superAdmin));
        $this->assertTrue($this->policy->update($superAdmin, $transaction));
        $this->assertTrue($this->policy->delete($superAdmin, $transaction));
        $this->assertTrue($this->policy->restore($superAdmin, $transaction));
        $this->assertTrue($this->policy->forceDelete($superAdmin, $transaction));
        $this->assertTrue($this->policy->export($superAdmin));
    }

    public function test_juban_treasurer_can_crud_and_export_but_cannot_force_delete_or_restore(): void
    {
        $treasurer = $this->makeUser(UserRole::JUBAN_TREASURER);
        $transaction = $this->makeTransaction();

        $this->assertTrue($this->policy->viewAny($treasurer));
        $this->assertTrue($this->policy->view($treasurer, $transaction));
        $this->assertTrue($this->policy->create($treasurer));
        $this->assertTrue($this->policy->update($treasurer, $transaction));
        $this->assertTrue($this->policy->delete($treasurer, $transaction));
        $this->assertTrue($this->policy->export($treasurer));

        // Auditing restrictions: cannot restore or permanently delete
        $this->assertFalse($this->policy->restore($treasurer, $transaction));
        $this->assertFalse($this->policy->forceDelete($treasurer, $transaction));
    }

    public function test_convention_editor_secretary_can_view_and_export_and_cover_crud_but_cannot_delete(): void
    {
        $secretary = $this->makeUser(UserRole::CONVENTION_EDITOR);
        $transaction = $this->makeTransaction();

        $this->assertTrue($this->policy->viewAny($secretary));
        $this->assertTrue($this->policy->view($secretary, $transaction));
        $this->assertTrue($this->policy->create($secretary));
        $this->assertTrue($this->policy->update($secretary, $transaction));
        $this->assertTrue($this->policy->export($secretary));

        // Strict mandate: Secretary cannot delete transactions under any circumstance
        $this->assertFalse($this->policy->delete($secretary, $transaction));
        $this->assertFalse($this->policy->restore($secretary, $transaction));
        $this->assertFalse($this->policy->forceDelete($secretary, $transaction));
    }

    public function test_other_roles_have_no_access_to_transactions(): void
    {
        $roles = [
            UserRole::MINISTER,
            UserRole::AUTHOR,
            UserRole::CHURCH_REPRESENTATIVE,
        ];

        $transaction = $this->makeTransaction();

        foreach ($roles as $role) {
            $user = $this->makeUser($role);

            $this->assertFalse($this->policy->viewAny($user));
            $this->assertFalse($this->policy->view($user, $transaction));
            $this->assertFalse($this->policy->create($user));
            $this->assertFalse($this->policy->update($user, $transaction));
            $this->assertFalse($this->policy->delete($user, $transaction));
            $this->assertFalse($this->policy->restore($user, $transaction));
            $this->assertFalse($this->policy->forceDelete($user, $transaction));
            $this->assertFalse($this->policy->export($user));
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
