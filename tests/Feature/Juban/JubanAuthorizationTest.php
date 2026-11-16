<?php

namespace Tests\Feature\Juban;

use App\Enums\UserRole;
use App\Models\JubanCategory;
use App\Models\JubanTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class JubanAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_gates_resolve_policies_for_juban_models(): void
    {
        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin);

        $this->assertTrue(Gate::allows('viewAny', JubanTransaction::class));
        $this->assertTrue(Gate::allows('viewAny', JubanCategory::class));
    }

    public function test_view_any_is_visible_only_to_authorized_treasury_and_secretariat_personas(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $secretary = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);

        $unauthorizedRoles = [
            UserRole::MINISTER,
            UserRole::AUTHOR,
            UserRole::CHURCH_REPRESENTATIVE,
        ];

        foreach ([$superAdmin, $treasurer, $secretary] as $authorizedUser) {
            $this->actingAs($authorizedUser);
            $this->assertTrue(Gate::allows('viewAny', JubanTransaction::class), "User {$authorizedUser->role->value} should have viewAny for transactions");
            $this->assertTrue(Gate::allows('viewAny', JubanCategory::class), "User {$authorizedUser->role->value} should have viewAny for categories");
        }

        foreach ($unauthorizedRoles as $role) {
            $user = User::factory()->create(['role' => $role, 'is_active' => true]);
            $this->actingAs($user);
            $this->assertFalse(Gate::allows('viewAny', JubanTransaction::class), "User {$role->value} must not have viewAny for transactions");
            $this->assertFalse(Gate::allows('viewAny', JubanCategory::class), "User {$role->value} must not have viewAny for categories");
        }
    }

    public function test_secretary_can_view_and_export_transactions_but_cannot_delete(): void
    {
        $secretary = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);
        $category = JubanCategory::factory()->create();
        $transaction = JubanTransaction::factory()->create(['category_id' => $category->id]);

        $this->actingAs($secretary);

        $this->assertTrue(Gate::allows('view', $transaction));
        $this->assertTrue(Gate::allows('export', JubanTransaction::class));
        $this->assertTrue(Gate::allows('create', JubanTransaction::class));
        $this->assertTrue(Gate::allows('update', $transaction));

        // Strict mandate: Secretary cannot delete
        $this->assertFalse(Gate::allows('delete', $transaction));
        $this->assertFalse(Gate::allows('forceDelete', $transaction));
        $this->assertFalse(Gate::allows('restore', $transaction));
    }

    public function test_treasurer_and_super_admin_can_delete_transactions_with_soft_delete(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);

        $category = JubanCategory::factory()->create();
        $transaction = JubanTransaction::factory()->create(['category_id' => $category->id]);

        $this->actingAs($superAdmin);
        $this->assertTrue(Gate::allows('delete', $transaction));

        $this->actingAs($treasurer);
        $this->assertTrue(Gate::allows('delete', $transaction));
    }

    public function test_cannot_delete_category_that_has_linked_transactions(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);

        $categoryWithTransactions = JubanCategory::factory()->create();
        JubanTransaction::factory()->create(['category_id' => $categoryWithTransactions->id]);

        $emptyCategory = JubanCategory::factory()->create();

        // With transactions: rejected for everyone
        $this->actingAs($superAdmin);
        $this->assertFalse(Gate::allows('delete', $categoryWithTransactions));

        $this->actingAs($treasurer);
        $this->assertFalse(Gate::allows('delete', $categoryWithTransactions));

        // Empty category: allowed for super admin and treasurer
        $this->actingAs($superAdmin);
        $this->assertTrue(Gate::allows('delete', $emptyCategory));

        $this->actingAs($treasurer);
        $this->assertTrue(Gate::allows('delete', $emptyCategory));
    }

    public function test_inactive_user_is_denied_any_juban_ability_even_if_super_admin(): void
    {
        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($inactiveAdmin);

        $this->assertFalse(Gate::allows('viewAny', JubanTransaction::class));
        $this->assertFalse(Gate::allows('viewAny', JubanCategory::class));
        $this->assertFalse(Gate::allows('create', JubanTransaction::class));
    }
}
