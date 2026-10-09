<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Models\Church;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class UserResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $conventionEditor;

    private User $churchRep;

    private User $author;

    private Church $church;

    protected function setUp(): void
    {
        parent::setUp();

        $this->church = Church::factory()->create(['name' => 'Primeira IBN de Goiânia']);

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
            'church_id' => $this->church->id,
            'is_active' => true,
        ]);

        $this->author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);
    }

    public function test_super_admin_and_convention_editor_can_access_user_pages_on_admin_panel(): void
    {
        $targetUser = User::factory()->create(['role' => UserRole::AUTHOR]);

        $this->actingAs($this->superAdmin)
            ->get('/admin/users')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get('/admin/users/create')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get("/admin/users/{$targetUser->id}/edit")
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/users')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/users/create')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get("/admin/users/{$targetUser->id}/edit")
            ->assertSuccessful();
    }

    public function test_non_admin_roles_and_inactive_users_cannot_access_user_resource(): void
    {
        $targetUser = User::factory()->create(['role' => UserRole::AUTHOR]);

        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($this->churchRep)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($this->author)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($inactiveAdmin)
            ->get('/admin/users')
            ->assertForbidden();

        $this->actingAs($this->churchRep)
            ->get("/admin/users/{$targetUser->id}/edit")
            ->assertForbidden();
    }

    public function test_can_render_user_list_table_columns_and_records(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->actingAs($this->superAdmin);

        Livewire::test(ListUsers::class)
            ->assertCanSeeTableRecords([$this->superAdmin, $this->churchRep])
            ->assertCanRenderTableColumn('name')
            ->assertCanRenderTableColumn('email')
            ->assertCanRenderTableColumn('role')
            ->assertCanRenderTableColumn('is_active');
    }

    public function test_user_resource_eager_loads_church_relationship(): void
    {
        $query = UserResource::getEloquentQuery();

        $this->assertTrue(
            array_key_exists('church', $query->getEagerLoads()),
            'O relacionamento church deve ser eager-loaded para prevenir N+1.'
        );
    }

    public function test_super_admin_can_create_user_with_role_and_church_assignment(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Pastor Marcos Silva',
                'email' => 'pr.marcos@ibngoiania.org.br',
                'password' => 'senhaSegura123',
                'role' => UserRole::CHURCH_REPRESENTATIVE->value,
                'church_id' => $this->church->id,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'name' => 'Pastor Marcos Silva',
            'email' => 'pr.marcos@ibngoiania.org.br',
            'role' => UserRole::CHURCH_REPRESENTATIVE->value,
            'church_id' => $this->church->id,
            'is_active' => 1,
        ]);

        $created = User::where('email', 'pr.marcos@ibngoiania.org.br')->first();
        $this->assertNotNull($created);
        $this->assertTrue(Hash::check('senhaSegura123', $created->password));
    }

    public function test_cannot_create_user_with_missing_required_fields(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => null,
                'email' => null,
                'password' => null,
                'role' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'email' => 'required',
                'password' => 'required',
                'role' => 'required',
            ]);
    }

    public function test_cannot_create_user_with_duplicate_email(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Outro Usuário',
                'email' => $this->author->email,
                'password' => 'senhaSegura123',
                'role' => UserRole::AUTHOR->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['email' => 'unique']);
    }

    public function test_church_representative_requires_church_id(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Representante Sem Igreja',
                'email' => 'sem.igreja@cbngo.org.br',
                'password' => 'senhaSegura123',
                'role' => UserRole::CHURCH_REPRESENTATIVE->value,
                'church_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['church_id' => 'required']);
    }

    public function test_super_admin_can_reset_password_via_action(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $targetUser = User::factory()->create([
            'password' => Hash::make('antigaSenha123'),
        ]);

        Livewire::test(ListUsers::class)
            ->callTableAction('resetPassword', $targetUser, [
                'new_password' => 'novaSenhaRedefinida999',
            ])
            ->assertHasNoTableActionErrors();

        $targetUser->refresh();
        $this->assertTrue(Hash::check('novaSenhaRedefinida999', $targetUser->password));
    }

    public function test_super_admin_can_send_password_reset_notification(): void
    {
        Notification::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $targetUser = User::factory()->create();

        Livewire::test(ListUsers::class)
            ->callTableAction('sendResetLink', $targetUser)
            ->assertHasNoTableActionErrors();

        Notification::assertSentTo($targetUser, ResetPassword::class);
    }

    public function test_super_admin_can_update_user(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $targetUser = User::factory()->create([
            'name' => 'Nome Antigo',
            'role' => UserRole::AUTHOR,
        ]);

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->fillForm([
                'name' => 'Nome Atualizado Pelo Admin',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'name' => 'Nome Atualizado Pelo Admin',
        ]);
    }

    public function test_super_admin_cannot_delete_themselves(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $this->superAdmin->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }

    public function test_convention_editor_cannot_create_or_assign_super_admin_role(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->conventionEditor);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'Tentativa Super Admin',
                'email' => 'hacked.admin@cbngo.org.br',
                'password' => 'senhaSegura123',
                'role' => UserRole::SUPER_ADMIN->value,
            ])
            ->call('create')
            ->assertHasFormErrors(['role']);

        $this->assertDatabaseMissing('users', [
            'email' => 'hacked.admin@cbngo.org.br',
        ]);
    }

    public function test_convention_editor_cannot_promote_another_user_to_super_admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->conventionEditor);

        $targetUser = User::factory()->create([
            'role' => UserRole::AUTHOR,
        ]);

        Livewire::test(EditUser::class, ['record' => $targetUser->getKey()])
            ->fillForm([
                'role' => UserRole::SUPER_ADMIN->value,
            ])
            ->call('save')
            ->assertHasFormErrors(['role']);

        $this->assertSame(UserRole::AUTHOR, $targetUser->fresh()->role);
    }

    public function test_convention_editor_cannot_reset_password_or_send_reset_link_to_super_admin(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->conventionEditor);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('resetPassword', $this->superAdmin)
            ->assertTableActionHidden('sendResetLink', $this->superAdmin);
    }

    public function test_send_reset_link_is_hidden_for_inactive_user(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $inactiveUser = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => false,
        ]);

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('sendResetLink', $inactiveUser);
    }

    public function test_user_cannot_deactivate_themselves_in_edit_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(EditUser::class, ['record' => $this->superAdmin->getKey()])
            ->fillForm([
                'is_active' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue((bool) $this->superAdmin->fresh()->is_active);
    }
}
