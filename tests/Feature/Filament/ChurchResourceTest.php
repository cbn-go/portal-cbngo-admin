<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\ChurchResource\Pages\CreateChurch;
use App\Filament\Resources\ChurchResource\Pages\EditChurch;
use App\Filament\Resources\ChurchResource\Pages\ListChurches;
use App\Models\Church;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ChurchResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $conventionEditor;

    private User $churchRep;

    private User $author;

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
    }

    public function test_super_admin_and_convention_editor_can_access_church_pages_on_admin_panel(): void
    {
        $church = Church::factory()->create();

        $this->actingAs($this->superAdmin)
            ->get('/admin/churches')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get('/admin/churches/create')
            ->assertSuccessful();

        $this->actingAs($this->superAdmin)
            ->get("/admin/churches/{$church->id}/edit")
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/churches')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get('/admin/churches/create')
            ->assertSuccessful();

        $this->actingAs($this->conventionEditor)
            ->get("/admin/churches/{$church->id}/edit")
            ->assertSuccessful();
    }

    public function test_non_admin_roles_and_inactive_users_cannot_access_church_resource(): void
    {
        $church = Church::factory()->create();

        $inactiveAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => false,
        ]);

        $this->actingAs($this->churchRep)
            ->get('/admin/churches')
            ->assertForbidden();

        $this->actingAs($this->author)
            ->get('/admin/churches')
            ->assertForbidden();

        $this->actingAs($inactiveAdmin)
            ->get('/admin/churches')
            ->assertForbidden();

        $this->actingAs($this->churchRep)
            ->get("/admin/churches/{$church->id}/edit")
            ->assertForbidden();
    }

    public function test_can_render_church_list_table_columns_and_records(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $church = Church::factory()->create([
            'name' => 'Igreja Batista Nacional Luz do Mundo',
            'city' => 'Goiânia',
            'pastor_name' => 'Pr. João Batista',
            'is_active' => true,
        ]);

        $this->actingAs($this->superAdmin);

        Livewire::test(ListChurches::class)
            ->assertCanSeeTableRecords([$church])
            ->assertCanRenderTableColumn('name')
            ->assertCanRenderTableColumn('city')
            ->assertCanRenderTableColumn('pastor_name')
            ->assertCanRenderTableColumn('is_active');
    }

    public function test_super_admin_can_create_church_with_logo_and_complete_data(): void
    {
        Storage::fake('public');
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $logo = UploadedFile::fake()->image('logo-igreja.png', 400, 400);

        Livewire::test(CreateChurch::class)
            ->fillForm([
                'name' => 'Igreja Batista Nacional Ebenézer',
                'cnpj' => '12.345.678/0001-90',
                'registration_number' => 'ROL-2026-GO',
                'pastor_name' => 'Pr. Davi Ferreira',
                'city' => 'Aparecida de Goiânia',
                'state' => 'GO',
                'neighborhood' => 'Setor Garavelo',
                'zip_code' => '74930-000',
                'address' => 'Avenida Tropical',
                'number' => '100',
                'complement' => 'Quadra 10 Lote 15',
                'phone' => '(62) 3200-1122',
                'cellphone' => '(62) 99888-7766',
                'email' => 'contato@ibnebenezer.org.br',
                'social_links' => [
                    'instagram' => 'https://instagram.com/ibnebenezer',
                    'site' => 'https://ibnebenezer.org.br',
                ],
                'logo' => $logo,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('churches', [
            'name' => 'Igreja Batista Nacional Ebenézer',
            'cnpj' => '12.345.678/0001-90',
            'pastor_name' => 'Pr. Davi Ferreira',
            'city' => 'Aparecida de Goiânia',
            'state' => 'GO',
            'is_active' => 1,
        ]);

        $church = Church::where('name', 'Igreja Batista Nacional Ebenézer')->first();
        $this->assertNotNull($church);
        $this->assertNotNull($church->logo);
        Storage::disk('public')->assertExists($church->logo);
        $this->assertSame('https://instagram.com/ibnebenezer', $church->social_links['instagram']);
    }

    public function test_cannot_create_church_with_missing_required_fields(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateChurch::class)
            ->fillForm([
                'name' => null,
                'city' => null,
            ])
            ->call('create')
            ->assertHasFormErrors([
                'name' => 'required',
                'city' => 'required',
            ]);
    }

    public function test_super_admin_can_update_church(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $church = Church::factory()->create([
            'name' => 'Nome Antigo da Congregação',
            'pastor_name' => 'Pastor Antigo',
        ]);

        Livewire::test(EditChurch::class, ['record' => $church->getKey()])
            ->fillForm([
                'name' => 'Nome Renovado da Congregação',
                'pastor_name' => 'Pr. Carlos Eduardo',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('churches', [
            'id' => $church->id,
            'name' => 'Nome Renovado da Congregação',
            'pastor_name' => 'Pr. Carlos Eduardo',
        ]);
    }

    public function test_super_admin_can_delete_church(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $church = Church::factory()->create();

        Livewire::test(EditChurch::class, ['record' => $church->getKey()])
            ->callAction(DeleteAction::class);

        $this->assertModelMissing($church);
    }

    public function test_cannot_delete_church_if_has_associated_users_or_news(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $churchWithUser = Church::factory()->create();
        User::factory()->create(['church_id' => $churchWithUser->id]);

        Livewire::test(EditChurch::class, ['record' => $churchWithUser->getKey()])
            ->assertActionHidden(DeleteAction::class);
    }
}
