<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\UserRole;
use App\Filament\Resources\FiscalCouncilMemberResource\Pages\CreateFiscalCouncilMember;
use App\Filament\Resources\FiscalCouncilMemberResource\Pages\EditFiscalCouncilMember;
use App\Filament\Resources\FiscalCouncilMemberResource\Pages\ListFiscalCouncilMembers;
use App\Models\FiscalCouncilMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class FiscalCouncilMemberResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_list_fiscal_council_members(): void
    {
        $this->actingAs($this->superAdmin);

        $member = FiscalCouncilMember::query()->create([
            'user_id' => User::factory()->create(['name' => 'Pr. Abílio'])->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        Livewire::test(ListFiscalCouncilMembers::class)
            ->assertCanSeeTableRecords([$member])
            ->assertSuccessful();
    }

    public function test_super_admin_can_create_fiscal_council_member(): void
    {
        $this->actingAs($this->superAdmin);

        $user = User::factory()->create(['name' => 'Juarez Raimundo']);

        Livewire::test(CreateFiscalCouncilMember::class)
            ->fillForm([
                'user_id' => $user->id,
                'seat' => FiscalCouncilSeat::VOGAL_1->value,
                'title' => 'Dc.',
                'qualification' => 'Administrador',
                'term_starts_on' => '2026-01-01',
                'term_ends_on' => '2028-12-31',
                'active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('fiscal_council_members', [
            'user_id' => $user->id,
            'seat' => FiscalCouncilSeat::VOGAL_1->value,
            'title' => 'Dc.',
            'qualification' => 'Administrador',
            'active' => 1,
        ]);
    }

    public function test_super_admin_can_edit_and_deactivate_member(): void
    {
        $this->actingAs($this->superAdmin);

        $user = User::factory()->create(['name' => 'Marilane Cristina']);
        $member = FiscalCouncilMember::query()->create([
            'user_id' => $user->id,
            'seat' => FiscalCouncilSeat::VOGAL_2,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        Livewire::test(EditFiscalCouncilMember::class, ['record' => $member->getRouteKey()])
            ->fillForm([
                'active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertFalse($member->active);
    }

    public function test_cannot_create_duplicate_active_seat(): void
    {
        $this->actingAs($this->superAdmin);

        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        FiscalCouncilMember::query()->create([
            'user_id' => $user1->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        Livewire::test(CreateFiscalCouncilMember::class)
            ->fillForm([
                'user_id' => $user2->id,
                'seat' => FiscalCouncilSeat::RELATOR->value,
                'term_starts_on' => '2026-01-01',
                'term_ends_on' => '2028-12-31',
                'active' => true,
            ])
            ->call('create')
            ->assertHasFormErrors(['seat']);
    }

    public function test_other_roles_cannot_access_fiscal_council_member_resource_in_admin(): void
    {
        $editor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        $this->actingAs($editor)
            ->get('/admin/fiscal-council-members')
            ->assertForbidden();
    }
}
