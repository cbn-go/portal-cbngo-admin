<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\MinisterResource;
use App\Filament\Resources\MinisterResource\Pages\CreateMinister;
use App\Filament\Resources\MinisterResource\Pages\EditMinister;
use App\Filament\Resources\MinisterResource\Pages\ListMinisters;
use App\Filament\Resources\MinisterResource\RelationManagers\HistoryEventsRelationManager;
use App\Models\Minister;
use App\Models\MinisterHistoryEvent;
use App\Models\User;
use App\Support\Cpf;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MinisterResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $conventionEditor;

    private User $churchRep;

    private User $author;

    private User $ministerUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->conventionEditor = User::factory()->create([
            'role' => UserRole::CONVENTION_EDITOR,
            'is_active' => true,
        ]);

        $this->churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);

        $this->author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        $this->ministerUser = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_super_admin_and_editor_can_open_list_and_create(): void
    {
        $this->actingAs($this->superAdmin)
            ->get('/admin/ministers')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get('/admin/ministers/create')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/ministers')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/ministers/create')
            ->assertSuccessful();
    }

    public function test_roles_without_admin_access_receive_forbidden(): void
    {
        $minister = Minister::factory()->create();

        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
        ]);

        foreach ([$this->ministerUser, $this->author, $this->churchRep, $inactiveAdmin] as $user) {
            $this->actingAs($user)
                ->get('/admin/ministers')
                ->assertForbidden();

            $this->actingAs($user)
                ->get("/admin/ministers/{$minister->id}")
                ->assertForbidden();

            $this->actingAs($user)
                ->get("/admin/ministers/{$minister->id}/edit")
                ->assertForbidden();
        }

        $this->assertTrue($this->superAdmin->can('viewAny', Minister::class));
        $this->assertTrue($this->conventionEditor->can('viewAny', Minister::class));
        $this->assertFalse($this->ministerUser->can('viewAny', Minister::class));
        $this->assertFalse($this->author->can('viewAny', Minister::class));
        $this->assertFalse($this->churchRep->can('viewAny', Minister::class));
        $this->assertFalse($inactiveAdmin->can('view', $minister));
    }

    public function test_listing_masks_cpf_and_view_shows_the_full_number(): void
    {
        $minister = Minister::factory()->create([
            'cpf' => '52998224725',
            'name' => 'Pr. Máscara',
        ]);

        $this->actingAs($this->conventionEditor);

        Livewire::test(ListMinisters::class)
            ->assertTableColumnFormattedStateSet('cpf', '***.***.***-25', $minister);

        $this->get("/admin/ministers/{$minister->id}")
            ->assertSuccessful()
            ->assertSee(Cpf::format('52998224725'));
    }

    public function test_table_searches_by_name_cpf_digits_and_registration(): void
    {
        $target = Minister::factory()->create([
            'name' => 'Helena Duarte',
            'registration_number' => '7788',
            'cpf' => '52998224725',
        ]);

        $other = Minister::factory()->create([
            'name' => 'Otávio Lima',
            'registration_number' => '1122',
            'cpf' => '39053344705',
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(ListMinisters::class)
            ->searchTable('Helena')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('52998224725')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other])
            ->searchTable('7788')
            ->assertCanSeeTableRecords([$target])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_ordination_filter_keeps_ministers_with_history_code_two(): void
    {
        $ordained = Minister::factory()->create(['name' => 'Pastor Ordenado']);
        MinisterHistoryEvent::query()->create([
            'minister_id' => $ordained->id,
            'occurred_on' => '2001-03-10',
            'code' => '2',
            'description' => 'Ordenação',
        ]);

        $withoutOrdination = Minister::factory()->create(['name' => 'Pastor Sem Ordenação']);

        $this->actingAs($this->superAdmin);

        Livewire::test(ListMinisters::class)
            ->filterTable('has_ordination', true)
            ->assertCanSeeTableRecords([$ordained])
            ->assertCanNotSeeTableRecords([$withoutOrdination])
            ->filterTable('has_ordination', false)
            ->assertCanSeeTableRecords([$withoutOrdination])
            ->assertCanNotSeeTableRecords([$ordained]);
    }

    public function test_only_super_admin_can_edit_ministerial_history(): void
    {
        $minister = Minister::factory()->create();
        $event = MinisterHistoryEvent::query()->create([
            'minister_id' => $minister->id,
            'occurred_on' => '2005-08-20',
            'code' => '2',
            'description' => 'Ordenação original',
        ]);

        $this->assertFalse($this->conventionEditor->can('manageHistory', $minister));
        $this->assertTrue($this->superAdmin->can('manageHistory', $minister));

        $this->actingAs($this->conventionEditor);

        Livewire::test(HistoryEventsRelationManager::class, [
            'ownerRecord' => $minister,
            'pageClass' => EditMinister::class,
        ])
            ->assertTableActionHidden('create')
            ->assertTableActionHidden('edit', $event)
            ->assertTableActionHidden('delete', $event);

        $this->actingAs($this->superAdmin);

        Livewire::test(HistoryEventsRelationManager::class, [
            'ownerRecord' => $minister,
            'pageClass' => EditMinister::class,
        ])
            ->callTableAction('edit', $event, data: [
                'occurred_on' => '2005-08-20',
                'code' => '2',
                'description' => 'Ordenação revisada',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertSame('Ordenação revisada', $event->refresh()->description);
    }

    public function test_activate_deactivate_and_password_reset_sync_the_linked_user(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'cpf' => '52998224725',
            'password' => 'senha-definida',
            'must_set_password' => false,
            'is_active' => true,
        ]);

        $minister = Minister::factory()->create([
            'cpf' => '52998224725',
            'status' => Minister::STATUS_ACTIVE,
            'user_id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $unlinked = Minister::factory()->create(['user_id' => null]);

        $this->actingAs($this->conventionEditor);

        Livewire::test(ListMinisters::class)
            ->assertTableActionHidden('forcePasswordReset', $unlinked)
            ->assertTableActionHidden('activate', $minister)
            ->callTableAction('deactivate', $minister);

        $minister->refresh();
        $user->refresh();

        $this->assertSame(Minister::STATUS_INACTIVE, $minister->status);
        $this->assertFalse($user->is_active);

        Livewire::test(ListMinisters::class)
            ->callTableAction('activate', $minister);

        $minister->refresh();
        $user->refresh();

        $this->assertSame(Minister::STATUS_ACTIVE, $minister->status);
        $this->assertTrue($user->is_active);

        Livewire::test(ListMinisters::class)
            ->callTableAction('forcePasswordReset', $minister);

        $user->refresh();

        $this->assertNull($user->password);
        $this->assertTrue($user->must_set_password);
    }

    public function test_creating_a_minister_also_creates_the_access_user(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateMinister::class)
            ->fillForm([
                'name' => 'Pastor Marcos Silva',
                'registration_number' => '1001',
                'function' => 'Pastor',
                'status' => Minister::STATUS_INACTIVE,
                'cpf' => '529.982.247-25',
                'email' => 'pr.marcos@ibngoiania.org.br',
                'sectional' => 'ORMIBAN GO',
                'church_name' => 'Igreja Batista Central',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $minister = Minister::query()->where('registration_number', '1001')->first();

        $this->assertNotNull($minister);
        $this->assertSame('52998224725', $minister->cpf);
        $this->assertNotNull($minister->user);
        $this->assertSame(UserRole::MINISTER, $minister->user->role);
        $this->assertSame('pr.marcos@ibngoiania.org.br', $minister->user->email);
        $this->assertSame('52998224725', $minister->user->cpf);
        $this->assertTrue($minister->user->must_set_password);
        $this->assertNull($minister->user->password);
        $this->assertFalse($minister->user->is_active);
    }

    public function test_query_eager_loads_user_and_history(): void
    {
        $eager = MinisterResource::getEloquentQuery()->getEagerLoads();

        $this->assertArrayHasKey('user', $eager);
        $this->assertArrayHasKey('historyEvents', $eager);
    }
}
