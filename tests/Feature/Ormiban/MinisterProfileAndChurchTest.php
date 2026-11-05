<?php

namespace Tests\Feature\Ormiban;

use App\Enums\UserRole;
use App\Filament\Ormiban\Pages\EditMinisterProfile;
use App\Filament\Ormiban\Pages\EditMyChurch;
use App\Models\Church;
use App\Models\Minister;
use App\Models\Municipality;
use App\Models\User;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class MinisterProfileAndChurchTest extends TestCase
{
    use RefreshDatabase;

    public function test_minister_can_access_own_profile_page(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $minister = Minister::factory()->create([
            'user_id' => $user->id,
            'cpf' => '52998224725',
            'registration_number' => 'ROL-7001',
            'status' => 'ATIVO(A)',
            'function' => 'Pastor',
        ]);

        $response = $this->actingAs($user)->get('/ormiban/profile');
        $response->assertSuccessful();
        $response->assertSee('ROL-7001');
    }

    public function test_profile_page_prefills_minister_official_and_contact_data(): void
    {
        $user = User::factory()->create([
            'name' => 'Pastor Teste',
            'email' => 'pastor@teste.org.br',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $minister = Minister::factory()->create([
            'user_id' => $user->id,
            'name' => 'Pastor Teste',
            'email' => 'pastor@teste.org.br',
            'cpf' => '52998224725',
            'registration_number' => 'ROL-7001',
            'phone' => '(62) 98888-7777',
            'address' => 'Rua das Flores, 123',
            'zip_code' => '74000-000',
        ]);

        Livewire::actingAs($user)
            ->test(EditMinisterProfile::class)
            ->assertSet('data.email', 'pastor@teste.org.br')
            ->assertSet('data.phone', '(62) 98888-7777')
            ->assertSet('data.address', 'Rua das Flores, 123')
            ->assertSet('data.zip_code', '74000-000');
    }

    public function test_minister_can_update_editable_profile_fields(): void
    {
        $user = User::factory()->create([
            'name' => 'Pastor Antigo',
            'email' => 'pastor.antigo@teste.org.br',
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $minister = Minister::factory()->create([
            'user_id' => $user->id,
            'name' => 'Pastor Antigo',
            'email' => 'pastor.antigo@teste.org.br',
            'phone' => '(62) 91111-2222',
            'address' => 'Endereço Antigo',
            'zip_code' => '74000-000',
        ]);

        Livewire::actingAs($user)
            ->test(EditMinisterProfile::class)
            ->set('data.email', 'pastor.novo@teste.org.br')
            ->set('data.phone', '(62) 99999-8888')
            ->set('data.address', 'Av. Nova, 456')
            ->set('data.zip_code', '74111-222')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $minister->refresh();

        $this->assertSame('pastor.novo@teste.org.br', $user->email);
        $this->assertSame('pastor.novo@teste.org.br', $minister->email);
        $this->assertSame('(62) 99999-8888', $minister->phone);
        $this->assertSame('Av. Nova, 456', $minister->address);
        $this->assertSame('74111-222', $minister->zip_code);
    }

    public function test_minister_cannot_modify_immutable_official_fields(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $minister = Minister::factory()->create([
            'user_id' => $user->id,
            'registration_number' => 'ROL-OFICIAL',
            'cpf' => '52998224725',
            'status' => 'ATIVO(A)',
        ]);

        // Sending altered official fields should not update them in the database
        Livewire::actingAs($user)
            ->test(EditMinisterProfile::class)
            ->set('data.registration_number', 'ROL-HACKEADO')
            ->set('data.cpf', '11144477735')
            ->set('data.status', 'DESLIGADO')
            ->call('save');

        $minister->refresh();

        $this->assertSame('ROL-OFICIAL', $minister->registration_number);
        $this->assertSame('52998224725', $minister->cpf);
        $this->assertSame('ATIVO(A)', $minister->status);
    }

    public function test_minister_can_change_password_when_logged_in(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
            'password' => Hash::make('SenhaAntiga123!'),
        ]);

        Minister::factory()->create([
            'user_id' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(EditMinisterProfile::class)
            ->set('data.password', 'NovaSenhaSegura@2026')
            ->set('data.password_confirmation', 'NovaSenhaSegura@2026')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('NovaSenhaSegura@2026', (string) $user->password));
    }

    public function test_profile_renders_gracefully_when_user_has_no_minister_record(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/ormiban/profile');
        $response->assertSuccessful();
        $response->assertSee('Nenhuma ficha ministerial vinculada');
    }

    public function test_minister_can_access_own_church_page_and_update_it(): void
    {
        $church = Church::factory()->create([
            'name' => 'Igreja Batista Nacional Central de Anápolis',
            'phone' => '(62) 3333-1111',
            'email' => 'central@anapolis.org.br',
            'address' => 'Rua Central, 100',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'church_id' => $church->id,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $minister = Minister::factory()->create([
            'user_id' => $user->id,
            'church_id' => $church->id,
        ]);

        $response = $this->actingAs($user)->get('/ormiban/minha-igreja');
        $response->assertSuccessful();
        $response->assertSee('Igreja Batista Nacional Central de Anápolis');

        Livewire::actingAs($user)
            ->test(EditMyChurch::class)
            ->set('data.phone', '(62) 98765-4321')
            ->set('data.address', 'Avenida Principal, 500')
            ->call('save')
            ->assertHasNoErrors();

        $church->refresh();
        $this->assertSame('(62) 98765-4321', $church->phone);
        $this->assertSame('Avenida Principal, 500', $church->address);
    }

    public function test_auxiliary_pastor_updates_the_church_on_his_ficha(): void
    {
        $church = Church::factory()->create([
            'name' => 'Igreja Batista Nacional da Paz',
            'pastor_name' => 'Pastor Titular',
            'phone' => '(62) 3333-0000',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'church_id' => null,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        Minister::factory()->create([
            'user_id' => $user->id,
            'church_id' => $church->id,
            'function' => 'Pastor',
            'name' => 'Pastor Auxiliar',
        ]);

        Livewire::actingAs($user)
            ->test(EditMyChurch::class)
            ->set('data.phone', '(62) 98888-7777')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('(62) 98888-7777', $church->refresh()->phone);
        $this->assertSame('Pastor Titular', $church->pastor_name);
    }

    public function test_minister_cannot_update_church_belonging_to_another_pastor(): void
    {
        $churchA = Church::factory()->create(['name' => 'Igreja A']);
        $churchB = Church::factory()->create(['name' => 'Igreja B']);

        $pastorA = User::factory()->create([
            'role' => UserRole::MINISTER,
            'church_id' => $churchA->id,
            'is_active' => true,
            'must_set_password' => false,
        ]);
        Minister::factory()->create([
            'user_id' => $pastorA->id,
            'church_id' => $churchA->id,
        ]);

        // ChurchPolicy check: pastorA can update churchA, but CANNOT update churchB
        $this->assertTrue(Gate::forUser($pastorA)->allows('update', $churchA));
        $this->assertFalse(Gate::forUser($pastorA)->allows('update', $churchB));
    }

    public function test_my_church_page_renders_gracefully_when_minister_has_no_church(): void
    {
        $active = Church::factory()->create([
            'name' => 'Igreja Ativa Visivel',
            'city' => 'Goiânia',
            'is_active' => true,
        ]);
        Church::factory()->create([
            'name' => 'Igreja Inativa Oculta',
            'city' => 'Anápolis',
            'is_active' => false,
        ]);

        [$user] = $this->ministerWithoutChurch();

        $response = $this->actingAs($user)->get('/ormiban/minha-igreja');
        $response->assertSuccessful();
        $response->assertSee('Vincular a uma igreja já cadastrada');
        $response->assertSee('Cadastrar uma nova igreja');
        $response->assertDontSee('Nenhuma igreja vinculada');

        $this->assertFalse(Gate::forUser($user)->allows('create', Church::class));

        Livewire::actingAs($user)
            ->test(EditMyChurch::class)
            ->assertFormFieldExists('existing_church_id', function (Select $field) use ($active): bool {
                $options = $field->getOptions();

                return array_key_exists($active->id, $options)
                    && ! collect($options)->contains(fn (string $label): bool => str_contains($label, 'Igreja Inativa Oculta'));
            });
    }

    public function test_minister_can_link_an_existing_active_church(): void
    {
        $church = Church::factory()->create([
            'name' => 'Igreja Batista Nacional da Esperança',
            'city' => 'Anápolis',
            'is_active' => true,
        ]);
        Minister::factory()->create([
            'church_id' => $church->id,
            'church_name' => $church->name,
        ]);

        [$user, $minister] = $this->ministerWithoutChurch('Pastor Sem Igreja');

        Livewire::actingAs($user)
            ->test(EditMyChurch::class)
            ->set('data.mode', 'vincular')
            ->set('data.existing_church_id', $church->id)
            ->call('linkChurch')
            ->assertHasNoErrors()
            ->assertRedirect('/ormiban/minha-igreja');

        $user->refresh();
        $minister->refresh();

        $this->assertSame($church->id, $user->church_id);
        $this->assertSame($church->id, $minister->church_id);
        $this->assertSame('Igreja Batista Nacional da Esperança', $minister->church_name);

        $this->actingAs($user)
            ->get('/ormiban/minha-igreja')
            ->assertSuccessful()
            ->assertSee('Igreja Batista Nacional da Esperança')
            ->assertDontSee('Cadastrar uma nova igreja');
    }

    public function test_minister_can_create_and_link_a_new_church(): void
    {
        $municipality = Municipality::factory()->create(['name' => 'Goiânia']);
        [$user, $minister] = $this->ministerWithoutChurch('Pastor Fundador');

        Livewire::actingAs($user)
            ->test(EditMyChurch::class)
            ->set('data.mode', 'cadastrar')
            ->set('data.name', 'Igreja Nova do Ministro')
            ->set('data.municipality_id', $municipality->id)
            ->set('data.city', 'Goiânia')
            ->set('data.state', 'GO')
            ->set('data.phone', '(62) 99999-0000')
            ->call('createChurch')
            ->assertHasNoErrors()
            ->assertRedirect('/ormiban/minha-igreja');

        $church = Church::query()->where('name', 'Igreja Nova do Ministro')->first();
        $this->assertNotNull($church);
        $this->assertTrue($church->is_active);
        $this->assertSame('Pastor Fundador', $church->pastor_name);
        $this->assertSame($municipality->id, $church->municipality_id);
        $this->assertSame('Goiânia', $church->city);
        $this->assertSame('GO', $church->state);
        $this->assertSame('(62) 99999-0000', $church->phone);
        $this->assertNotEmpty($church->slug);

        $user->refresh();
        $minister->refresh();
        $this->assertSame($church->id, $user->church_id);
        $this->assertSame($church->id, $minister->church_id);
        $this->assertSame('Igreja Nova do Ministro', $minister->church_name);
    }

    public function test_minister_with_church_cannot_link_or_create_another(): void
    {
        $church = Church::factory()->create(['name' => 'Igreja Atual']);
        $other = Church::factory()->create(['name' => 'Outra Igreja', 'is_active' => true]);

        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'church_id' => $church->id,
            'is_active' => true,
            'must_set_password' => false,
        ]);
        $minister = Minister::factory()->create([
            'user_id' => $user->id,
            'name' => 'Pastor Vinculado',
            'church_id' => $church->id,
            'church_name' => 'Igreja Atual',
        ]);

        $before = Church::query()->count();

        Livewire::actingAs($user)
            ->test(EditMyChurch::class)
            ->set('data.existing_church_id', $other->id)
            ->set('data.mode', 'cadastrar')
            ->set('data.name', 'Igreja Que Não Deve Nascer')
            ->call('linkChurch')
            ->assertNotified('Você já possui uma igreja vinculada.');

        Livewire::actingAs($user)
            ->test(EditMyChurch::class)
            ->call('createChurch')
            ->assertNotified('Você já possui uma igreja vinculada.');

        $this->assertSame($before, Church::query()->count());
        $this->assertNull(Church::query()->where('name', 'Igreja Que Não Deve Nascer')->first());
        $this->assertSame($church->id, $user->refresh()->church_id);
        $this->assertSame($church->id, $minister->refresh()->church_id);
        $this->assertSame('Igreja Atual', $minister->church_name);
    }

    public function test_super_admin_without_church_keeps_the_secretariat_notice(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'church_id' => null,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $this->actingAs($user)
            ->get('/ormiban/minha-igreja')
            ->assertSuccessful()
            ->assertSee('Nenhuma igreja vinculada')
            ->assertDontSee('Cadastrar uma nova igreja');
    }

    /**
     * @return array{0: User, 1: Minister}
     */
    private function ministerWithoutChurch(string $name = 'Pastor Sem Igreja'): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'role' => UserRole::MINISTER,
            'church_id' => null,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $minister = Minister::factory()->create([
            'user_id' => $user->id,
            'name' => $name,
            'church_id' => null,
            'church_name' => 'Não informada',
        ]);

        return [$user, $minister];
    }

    public function test_other_roles_cannot_access_ormiban_pages(): void
    {
        $author = User::factory()->create([
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);

        $churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);

        $this->actingAs($author)->get('/ormiban')->assertForbidden();
        $this->actingAs($author)->get('/ormiban/profile')->assertForbidden();
        $this->actingAs($author)->get('/ormiban/minha-igreja')->assertForbidden();

        $this->actingAs($churchRep)->get('/ormiban')->assertForbidden();
        $this->actingAs($churchRep)->get('/ormiban/profile')->assertForbidden();
        $this->actingAs($churchRep)->get('/ormiban/minha-igreja')->assertForbidden();
    }
}
