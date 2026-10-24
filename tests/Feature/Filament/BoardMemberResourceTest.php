<?php

namespace Tests\Feature\Filament;

use App\Enums\BoardGroup;
use App\Enums\UserRole;
use App\Filament\Resources\BoardMemberResource\Pages\CreateBoardMember;
use App\Filament\Resources\BoardMemberResource\Pages\EditBoardMember;
use App\Filament\Resources\BoardMemberResource\Pages\ListBoardMembers;
use App\Models\BoardMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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
                'name' => 'Pr. Roberto Silveira',
                'role' => 'Presidente',
                'group' => BoardGroup::PRESIDENCIA->value,
                'term_label' => 'Gestão 2025–2027',
                'photo' => UploadedFile::fake()->image('roberto.jpg', 400, 400),
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $member = BoardMember::query()->where('name', 'Pr. Roberto Silveira')->firstOrFail();

        $this->assertSame(BoardGroup::PRESIDENCIA, $member->group);
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
