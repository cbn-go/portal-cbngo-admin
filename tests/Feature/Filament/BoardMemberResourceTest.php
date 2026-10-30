<?php

namespace Tests\Feature\Filament;

use App\Enums\AmigoRegion;
use App\Enums\BoardGroup;
use App\Enums\ClergyTitle;
use App\Enums\UserRole;
use App\Filament\Resources\BoardMemberResource;
use App\Filament\Resources\BoardMemberResource\Pages\CreateBoardMember;
use App\Filament\Resources\BoardMemberResource\Pages\EditBoardMember;
use App\Filament\Resources\BoardMemberResource\Pages\ListBoardMembers;
use App\Models\BoardMember;
use App\Models\User;
use Database\Seeders\BoardTerm20262029Seeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BoardMemberResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_can_access_board_member_pages(): void
    {
        $member = BoardMember::factory()->create();

        $this->actingAs($this->superAdmin)->get('/admin/board-members')->assertSuccessful();
        $this->actingAs($this->superAdmin)->get('/admin/board-members/create')->assertSuccessful();
        $this->actingAs($this->superAdmin)->get("/admin/board-members/{$member->id}/edit")->assertSuccessful();
    }

    public function test_other_roles_and_inactive_users_cannot_access_board_members(): void
    {
        $member = BoardMember::factory()->create();

        $users = [
            User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get('/admin/board-members')->assertForbidden();
            $this->actingAs($user)->get("/admin/board-members/{$member->id}/edit")->assertForbidden();
        }
    }

    public function test_super_admin_can_create_board_member_with_photo(): void
    {
        Storage::fake('media');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBoardMember::class)
            ->fillForm([
                'title' => ClergyTitle::PASTOR->value,
                'name' => 'André Luiz Rodrigues da Silva',
                'role' => 'Presidente',
                'group' => BoardGroup::PRESIDENCIA->value,
                'term_starts_on' => '2026-10-01',
                'term_ends_on' => '2029-09-30',
                'photo' => UploadedFile::fake()->image('andre.jpg', 400, 400),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = BoardMember::query()->where('name', 'André Luiz Rodrigues da Silva')->firstOrFail();

        $this->assertSame(BoardGroup::PRESIDENCIA, $member->group);
        $this->assertSame(ClergyTitle::PASTOR, $member->title);
        $this->assertSame('Pr. André Luiz Rodrigues da Silva', $member->display_name);
        $this->assertSame('Triênio 2026–2029', $member->term_label);
        $this->assertSame('2026-10-01', $member->term_starts_on?->toDateString());
        $this->assertSame(1, $member->position);
        $this->assertNotNull($member->photo);
        $this->assertStringStartsWith('board/photos/', $member->photo);
        Storage::disk('media')->assertExists($member->photo);
    }

    public function test_regional_coordinator_requires_region(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBoardMember::class)
            ->fillForm([
                'name' => 'Pr. Fernando Lopes',
                'role' => 'Coordenador Regional',
                'group' => BoardGroup::COORDENACAO_REGIONAL->value,
                'region' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['region' => 'required']);
    }

    public function test_regional_board_member_only_accepts_amigo_regions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $base = [
            'title' => ClergyTitle::PASTOR->value,
            'name' => 'Marcondes Nunes Pereira',
            'role' => 'Coordenador',
            'group' => BoardGroup::COORDENACAO_REGIONAL->value,
        ];

        Livewire::test(CreateBoardMember::class)
            ->fillForm([...$base, 'region' => 'Região Sudoeste (Rio Verde e Jataí)'])
            ->call('create')
            ->assertHasFormErrors(['region']);

        Livewire::test(CreateBoardMember::class)
            ->fillForm([...$base, 'region' => AmigoRegion::SUDOESTE->value])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame(AmigoRegion::SUDOESTE, BoardMember::query()->where('name', 'Marcondes Nunes Pereira')->firstOrFail()->region);
    }

    public function test_region_is_cleared_outside_regional_boards(): void
    {
        $member = BoardMember::factory()->create([
            'group' => BoardGroup::COORDENACAO_REGIONAL,
            'region' => AmigoRegion::NORTE,
        ]);

        $member->update(['group' => BoardGroup::CONPLEX]);

        $this->assertNull($member->refresh()->region);
    }

    public function test_term_end_cannot_precede_its_start(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBoardMember::class)
            ->fillForm([
                'name' => 'Irenice Ramos',
                'role' => 'Vogal',
                'group' => BoardGroup::CONPLEX->value,
                'term_starts_on' => '2026-10-01',
                'term_ends_on' => '2026-09-30',
            ])
            ->call('create')
            ->assertHasFormErrors(['term_ends_on']);
    }

    public function test_term_label_is_generated_from_dates(): void
    {
        $this->assertSame('Triênio 2026–2029', BoardMember::termLabelFor(Carbon::parse('2026-10-01'), Carbon::parse('2029-09-30')));
        $this->assertSame('Gestão 2024–2026', BoardMember::termLabelFor(Carbon::parse('2024-01-01'), Carbon::parse('2026-12-31')));
        $this->assertNull(BoardMember::termLabelFor(Carbon::parse('2026-10-01'), null));

        $legacy = BoardMember::factory()->create(['term_label' => 'Gestão 2022–2024']);
        $this->assertSame('Gestão 2022–2024', $legacy->term_label);
    }

    public function test_new_member_form_defaults_to_the_latest_term(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        BoardMember::factory()->create(['term_starts_on' => '2023-10-01', 'term_ends_on' => '2026-09-30']);
        BoardMember::factory()->create(['term_starts_on' => '2026-10-01', 'term_ends_on' => '2029-09-30']);

        Livewire::test(CreateBoardMember::class)
            ->assertFormSet(['term_starts_on' => '2026-10-01', 'term_ends_on' => '2029-09-30']);
    }

    public function test_table_can_be_filtered_by_current_term(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);
        $this->travelTo(Carbon::parse('2026-10-30'));

        $current = BoardMember::factory()->create(['term_starts_on' => '2026-10-01', 'term_ends_on' => '2029-09-30']);
        $previous = BoardMember::factory()->create(['term_starts_on' => '2023-10-01', 'term_ends_on' => '2026-09-30']);
        $undated = BoardMember::factory()->create();

        Livewire::test(ListBoardMembers::class)
            ->filterTable('current_term', true)
            ->assertCanSeeTableRecords([$current])
            ->assertCanNotSeeTableRecords([$previous, $undated]);
    }

    public function test_global_search_finds_members_by_name_and_role(): void
    {
        $this->actingAs($this->superAdmin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        BoardMember::factory()->create(['name' => 'Abílio Rocha Neto', 'role' => 'Relator']);

        $results = BoardMemberResource::getGlobalSearchResults('Relator');

        $this->assertCount(1, $results);
        $this->assertSame('Abílio Rocha Neto', $results->first()?->title);
    }

    public function test_term_2026_2029_seeder_is_idempotent_and_retires_previous_members(): void
    {
        $previous = BoardMember::factory()->create(['name' => 'Roberto Silveira', 'position' => 1]);
        $manual = BoardMember::factory()->create([
            'title' => null,
            'name' => 'Irenice Ramos',
            'role' => 'Vogal',
            'group' => BoardGroup::CONPLEX,
        ]);

        $this->seed(BoardTerm20262029Seeder::class);
        $this->seed(BoardTerm20262029Seeder::class);

        $this->assertSame(BoardTerm20262029Seeder::memberCount() + 1, BoardMember::query()->count());
        $this->assertSame(BoardTerm20262029Seeder::memberCount(), BoardMember::query()->active()->count());
        $this->assertFalse($previous->refresh()->is_active);
        $this->assertTrue($manual->refresh()->is_active);
        $this->assertSame('Triênio 2026–2029', $manual->term_label);
        $this->assertSame(BoardTerm20262029Seeder::memberCount() + 1, $previous->position);

        $president = BoardMember::query()->active()->where('group', BoardGroup::PRESIDENCIA)->sole();
        $this->assertSame('Pr. André Luiz Rodrigues da Silva', $president->display_name);
        $this->assertSame('Triênio 2026–2029', $president->term_label);
        $this->assertSame(1, $president->position);

        $this->assertSame(2, BoardMember::query()->where('name', 'Marcos Fialho Silva')->count());
        $this->assertSame(4, BoardMember::query()->active()->where('group', BoardGroup::CONPLEX)->count());
        $this->assertSame(2, BoardMember::query()->active()->where('group', BoardGroup::CONPLEX)->whereNull('title')->count());

        foreach (AmigoRegion::cases() as $region) {
            $this->assertSame(4, BoardMember::query()->where('region', $region)->count(), $region->value);
        }
    }

    public function test_cannot_upload_invalid_file_type_as_photo(): void
    {
        Storage::fake('media');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBoardMember::class)
            ->fillForm([
                'name' => 'Pr. Foto Inválida',
                'role' => 'Vogal',
                'group' => BoardGroup::CONSELHO_FISCAL->value,
                'photo' => UploadedFile::fake()->create('foto.pdf', 100, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasFormErrors(['photo']);

        $this->assertDatabaseMissing('board_members', ['name' => 'Pr. Foto Inválida']);
    }

    public function test_super_admin_can_update_board_member(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $member = BoardMember::factory()->create(['group' => BoardGroup::SECRETARIA, 'role' => '2º Secretário']);

        Livewire::test(EditBoardMember::class, ['record' => $member->getKey()])
            ->fillForm(['role' => '1º Secretário', 'is_active' => false])
            ->call('save')
            ->assertHasNoFormErrors();

        $member->refresh();
        $this->assertSame('1º Secretário', $member->role);
        $this->assertFalse($member->is_active);
    }

    public function test_new_members_are_appended_to_the_end(): void
    {
        $first = BoardMember::factory()->create(['group' => BoardGroup::TESOURARIA]);
        $second = BoardMember::factory()->create(['group' => BoardGroup::PRESIDENCIA]);
        $explicit = BoardMember::factory()->create(['position' => 10]);
        $next = BoardMember::factory()->create();

        $this->assertSame(1, $first->position);
        $this->assertSame(2, $second->position);
        $this->assertSame(10, $explicit->position);
        $this->assertSame(11, $next->position);
    }

    public function test_table_is_sorted_by_position_and_can_be_reordered(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $a = BoardMember::factory()->create(['group' => BoardGroup::CONSELHO_FISCAL]);
        $b = BoardMember::factory()->create(['group' => BoardGroup::CONSELHO_FISCAL]);
        $c = BoardMember::factory()->create(['group' => BoardGroup::CONSELHO_FISCAL]);

        Livewire::test(ListBoardMembers::class)
            ->assertCanSeeTableRecords([$a, $b, $c], inOrder: true)
            ->call('reorderTable', [(string) $c->id, (string) $a->id, (string) $b->id]);

        $this->assertSame(1, $c->refresh()->position);
        $this->assertSame(2, $a->refresh()->position);
        $this->assertSame(3, $b->refresh()->position);
    }

    public function test_table_can_be_filtered_by_group_and_term(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $current = BoardMember::factory()->create(['group' => BoardGroup::PRESIDENCIA, 'term_label' => 'Gestão 2025–2027']);
        $previous = BoardMember::factory()->create(['group' => BoardGroup::PRESIDENCIA, 'term_label' => 'Gestão 2022–2024']);
        $treasurer = BoardMember::factory()->create(['group' => BoardGroup::TESOURARIA, 'term_label' => 'Gestão 2025–2027']);

        Livewire::test(ListBoardMembers::class)
            ->filterTable('group', BoardGroup::PRESIDENCIA->value)
            ->assertCanSeeTableRecords([$current, $previous])
            ->assertCanNotSeeTableRecords([$treasurer]);

        Livewire::test(ListBoardMembers::class)
            ->filterTable('term_label', 'Gestão 2022–2024')
            ->assertCanSeeTableRecords([$previous])
            ->assertCanNotSeeTableRecords([$current, $treasurer]);
    }

    public function test_term_label_options_list_distinct_terms(): void
    {
        BoardMember::factory()->count(2)->create(['term_label' => 'Gestão 2025–2027']);
        BoardMember::factory()->create(['term_label' => 'Gestão 2022–2024']);
        BoardMember::factory()->create(['term_label' => null]);

        $this->assertSame(
            ['Gestão 2025–2027' => 'Gestão 2025–2027', 'Gestão 2022–2024' => 'Gestão 2022–2024'],
            BoardMember::termLabelOptions(),
        );
    }
}
