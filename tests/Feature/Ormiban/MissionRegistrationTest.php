<?php

namespace Tests\Feature\Ormiban;

use App\Enums\ChurchType;
use App\Enums\UserRole;
use App\Filament\Ormiban\Resources\MissionResource\Pages\CreateMission;
use App\Filament\Ormiban\Resources\MissionResource\Pages\ListMissions;
use App\Filament\Resources\ChurchResource\Pages\ListChurches;
use App\Models\Church;
use App\Models\Minister;
use App\Models\Municipality;
use App\Models\User;
use Database\Seeders\MunicipalitySeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class MissionRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MunicipalitySeeder::class);
    }

    public function test_default_church_type_is_church(): void
    {
        $church = Church::factory()->create([
            'name' => 'Igreja Batista Central',
        ]);

        $this->assertSame(ChurchType::CHURCH, $church->type);
        $this->assertTrue($church->isChurch());
        $this->assertFalse($church->isMission());
    }

    public function test_church_can_be_created_as_mission_with_parent_church(): void
    {
        $motherChurch = Church::factory()->create([
            'name' => 'Igreja Batista Mãe',
            'type' => ChurchType::CHURCH,
        ]);

        $mission = Church::factory()->create([
            'name' => 'Missão Batista Vale do Sol',
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurch->id,
        ]);

        $this->assertSame(ChurchType::MISSION, $mission->type);
        $this->assertTrue($mission->isMission());
        $this->assertFalse($mission->isChurch());
        $this->assertSame($motherChurch->id, $mission->parentChurch?->id);
        $this->assertTrue($motherChurch->missions->contains($mission));
    }

    public function test_mission_forces_cnpj_to_null_on_save(): void
    {
        $motherChurch = Church::factory()->create();

        $mission = Church::factory()->create([
            'name' => 'Missão sem CNPJ',
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurch->id,
            'cnpj' => '12.345.678/0001-99',
        ]);

        $this->assertNull($mission->fresh()->cnpj);
    }

    public function test_mission_synchronizes_pastor_name_with_leader_minister(): void
    {
        $minister = Minister::factory()->create([
            'name' => 'Pr. João Missionário',
        ]);

        $motherChurch = Church::factory()->create();

        $mission = Church::factory()->create([
            'name' => 'Missão Esperança',
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurch->id,
            'leader_minister_id' => $minister->id,
            'pastor_name' => null,
        ]);

        $this->assertSame('Pr. João Missionário', $mission->fresh()->pastor_name);
        $this->assertSame($minister->id, $mission->leaderMinister?->id);
    }

    public function test_scopes_igrejas_and_missoes(): void
    {
        $motherChurch = Church::factory()->create(['type' => ChurchType::CHURCH]);
        $mission = Church::factory()->create([
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurch->id,
        ]);

        $igrejas = Church::igrejas()->get();
        $missoes = Church::missoes()->get();

        $this->assertTrue($igrejas->contains($motherChurch));
        $this->assertFalse($igrejas->contains($mission));

        $this->assertTrue($missoes->contains($mission));
        $this->assertFalse($missoes->contains($motherChurch));
    }

    public function test_super_admin_can_manage_any_mission_in_church_policy(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $motherChurch = Church::factory()->create(['type' => ChurchType::CHURCH]);
        $mission = Church::factory()->create([
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurch->id,
        ]);

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Church::class));
        $this->assertTrue(Gate::forUser($admin)->allows('view', $mission));
        $this->assertTrue(Gate::forUser($admin)->allows('create', Church::class));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $mission));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $mission));
    }

    public function test_mother_church_minister_can_create_and_update_own_mission(): void
    {
        $motherChurch = Church::factory()->create(['type' => ChurchType::CHURCH]);

        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'church_id' => $motherChurch->id,
            'must_set_password' => false,
        ]);

        Minister::factory()->create([
            'user_id' => $user->id,
            'church_id' => $motherChurch->id,
        ]);

        $mission = Church::factory()->create([
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurch->id,
        ]);

        $this->assertTrue(Gate::forUser($user)->allows('create', Church::class));
        $this->assertTrue(Gate::forUser($user)->allows('view', $mission));
        $this->assertTrue(Gate::forUser($user)->allows('update', $mission));
        $this->assertTrue(Gate::forUser($user)->allows('delete', $mission));
    }

    public function test_leader_minister_can_view_and_update_mission_they_lead(): void
    {
        $motherChurch = Church::factory()->create(['type' => ChurchType::CHURCH]);

        $leaderUser = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $leaderMinister = Minister::factory()->create([
            'user_id' => $leaderUser->id,
        ]);

        $mission = Church::factory()->create([
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurch->id,
            'leader_minister_id' => $leaderMinister->id,
        ]);

        $this->assertTrue(Gate::forUser($leaderUser)->allows('view', $mission));
        $this->assertTrue(Gate::forUser($leaderUser)->allows('update', $mission));
    }

    public function test_unrelated_minister_cannot_view_or_update_other_church_mission(): void
    {
        $motherChurchA = Church::factory()->create(['type' => ChurchType::CHURCH]);
        $motherChurchB = Church::factory()->create(['type' => ChurchType::CHURCH]);

        $ministerB = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'church_id' => $motherChurchB->id,
            'must_set_password' => false,
        ]);

        Minister::factory()->create([
            'user_id' => $ministerB->id,
            'church_id' => $motherChurchB->id,
        ]);

        $missionA = Church::factory()->create([
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurchA->id,
        ]);

        $this->assertFalse(Gate::forUser($ministerB)->allows('view', $missionA));
        $this->assertFalse(Gate::forUser($ministerB)->allows('update', $missionA));
        $this->assertFalse(Gate::forUser($ministerB)->allows('delete', $missionA));
    }

    public function test_minister_without_church_cannot_create_mission(): void
    {
        $ministerWithoutChurch = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'church_id' => null,
            'must_set_password' => false,
        ]);

        Minister::factory()->create([
            'user_id' => $ministerWithoutChurch->id,
            'church_id' => null,
        ]);

        $this->assertFalse(Gate::forUser($ministerWithoutChurch)->allows('create', Church::class));
    }

    public function test_admin_church_resource_lists_and_filters_missions(): void
    {
        $admin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $motherChurch = Church::factory()->create([
            'name' => 'Primeira Igreja Batista',
            'type' => ChurchType::CHURCH,
        ]);

        $mission = Church::factory()->create([
            'name' => 'Congregação Missão Norte',
            'type' => ChurchType::MISSION,
            'parent_church_id' => $motherChurch->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($admin);

        Livewire::test(ListChurches::class)
            ->assertCanSeeTableRecords([$motherChurch, $mission])
            ->filterTable('type', ChurchType::MISSION->value)
            ->assertCanSeeTableRecords([$mission])
            ->assertCanNotSeeTableRecords([$motherChurch]);
    }

    public function test_ormiban_mission_resource_lists_only_accessible_missions(): void
    {
        $churchA = Church::factory()->create(['name' => 'Igreja A', 'type' => ChurchType::CHURCH]);
        $churchB = Church::factory()->create(['name' => 'Igreja B', 'type' => ChurchType::CHURCH]);

        $ministerA = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'church_id' => $churchA->id,
            'must_set_password' => false,
        ]);
        Minister::factory()->create([
            'user_id' => $ministerA->id,
            'church_id' => $churchA->id,
        ]);

        $missionA = Church::factory()->create([
            'name' => 'Missão da Igreja A',
            'type' => ChurchType::MISSION,
            'parent_church_id' => $churchA->id,
        ]);

        $missionB = Church::factory()->create([
            'name' => 'Missão da Igreja B',
            'type' => ChurchType::MISSION,
            'parent_church_id' => $churchB->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('ormiban'));
        $this->actingAs($ministerA);

        Livewire::test(ListMissions::class)
            ->assertCanSeeTableRecords([$missionA])
            ->assertCanNotSeeTableRecords([$missionB, $churchA, $churchB]);
    }

    public function test_ormiban_minister_creates_mission_with_auto_parent_church(): void
    {
        $motherChurch = Church::factory()->create([
            'name' => 'Igreja Batista Central',
            'type' => ChurchType::CHURCH,
        ]);

        $minister = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'church_id' => $motherChurch->id,
            'must_set_password' => false,
        ]);
        Minister::factory()->create([
            'user_id' => $minister->id,
            'church_id' => $motherChurch->id,
        ]);

        $municipality = Municipality::first() ?? Municipality::factory()->create();

        Filament::setCurrentPanel(Filament::getPanel('ormiban'));
        $this->actingAs($minister);

        Livewire::test(CreateMission::class)
            ->fillForm([
                'name' => 'Missão Bairro Novo',
                'municipality_id' => $municipality->id,
                'address' => 'Rua das Palmeiras, 10',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $mission = Church::where('name', 'Missão Bairro Novo')->first();
        $this->assertNotNull($mission);
        $this->assertSame(ChurchType::MISSION, $mission->type);
        $this->assertSame($motherChurch->id, $mission->parent_church_id);
        $this->assertNull($mission->cnpj);
    }

    public function test_ormiban_unrelated_minister_cannot_access_other_mission_edit(): void
    {
        $churchA = Church::factory()->create(['type' => ChurchType::CHURCH]);
        $churchB = Church::factory()->create(['type' => ChurchType::CHURCH]);

        $ministerB = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'church_id' => $churchB->id,
            'must_set_password' => false,
        ]);
        Minister::factory()->create([
            'user_id' => $ministerB->id,
            'church_id' => $churchB->id,
        ]);

        $missionA = Church::factory()->create([
            'name' => 'Missão da Igreja A',
            'type' => ChurchType::MISSION,
            'parent_church_id' => $churchA->id,
        ]);

        $this->assertFalse(Gate::forUser($ministerB)->allows('update', $missionA));

        Filament::setCurrentPanel(Filament::getPanel('ormiban'));
        $response = $this->actingAs($ministerB)->get("/ormiban/missoes/{$missionA->id}/edit");
        $this->assertTrue(in_array($response->getStatusCode(), [403, 404], true));
    }
}
