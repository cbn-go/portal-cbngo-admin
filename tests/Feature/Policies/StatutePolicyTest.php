<?php

namespace Tests\Feature\Policies;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Statute;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class StatutePolicyTest extends TestCase
{
    use RefreshDatabase;

    private string $validXml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validXml = file_get_contents(database_path('seeders/data/estatuto-2025-10-18.akn.xml'));
    }

    private function createStatute(PublishStatus $status = PublishStatus::DRAFT): Statute
    {
        return Statute::create([
            'title' => 'Estatuto da CBN-GO',
            'version_label' => 'v-'.uniqid(),
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
            'approval_body' => 'AG',
            'effective_at' => '2025-10-18',
            'status' => $status,
            'akn_xml' => $this->validXml,
        ]);
    }

    public function test_super_admin_has_full_access(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $statute = $this->createStatute();

        $this->assertTrue(Gate::forUser($superAdmin)->allows('viewAny', Statute::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('view', $statute));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('create', Statute::class));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('update', $statute));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('publish', $statute));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('archive', $statute));
        $this->assertTrue(Gate::forUser($superAdmin)->allows('delete', $statute));
    }

    public function test_convention_editor_can_create_and_edit_but_cannot_publish_or_delete(): void
    {
        $editor = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);
        $statute = $this->createStatute();

        $this->assertTrue(Gate::forUser($editor)->allows('viewAny', Statute::class));
        $this->assertTrue(Gate::forUser($editor)->allows('view', $statute));
        $this->assertTrue(Gate::forUser($editor)->allows('create', Statute::class));
        $this->assertTrue(Gate::forUser($editor)->allows('update', $statute));

        // Editor NÃO pode publicar nem arquivar nem excluir
        $this->assertFalse(Gate::forUser($editor)->allows('publish', $statute));
        $this->assertFalse(Gate::forUser($editor)->allows('archive', $statute));
        $this->assertFalse(Gate::forUser($editor)->allows('delete', $statute));
    }

    public function test_other_roles_cannot_manage_statutes(): void
    {
        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $churchRep = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]);
        $statute = $this->createStatute();

        foreach ([$author, $churchRep] as $user) {
            $this->assertFalse(Gate::forUser($user)->allows('viewAny', Statute::class));
            $this->assertFalse(Gate::forUser($user)->allows('view', $statute));
            $this->assertFalse(Gate::forUser($user)->allows('create', Statute::class));
            $this->assertFalse(Gate::forUser($user)->allows('update', $statute));
            $this->assertFalse(Gate::forUser($user)->allows('publish', $statute));
            $this->assertFalse(Gate::forUser($user)->allows('delete', $statute));
        }
    }

    public function test_inactive_users_are_forbidden(): void
    {
        $inactiveSuperAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]);
        $statute = $this->createStatute();

        $this->assertFalse(Gate::forUser($inactiveSuperAdmin)->allows('viewAny', Statute::class));
        $this->assertFalse(Gate::forUser($inactiveSuperAdmin)->allows('view', $statute));
        $this->assertFalse(Gate::forUser($inactiveSuperAdmin)->allows('publish', $statute));
    }
}
