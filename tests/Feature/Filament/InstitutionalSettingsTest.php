<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Pages\ManageInstitutionalSettings;
use App\Models\InstitutionalSetting;
use App\Models\User;
use Database\Seeders\InstitutionalSettingSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InstitutionalSettingsTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $conventionEditor;

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
    }

    public function test_super_admin_and_convention_editor_can_access_page(): void
    {
        foreach ([$this->superAdmin, $this->conventionEditor] as $user) {
            $this->actingAs($user)
                ->get('/admin/institutional-settings')
                ->assertSuccessful();
        }
    }

    public function test_unauthorized_roles_and_inactive_users_cannot_access(): void
    {
        $unauthorized = [
            User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]),
        ];

        foreach ($unauthorized as $user) {
            $this->actingAs($user)
                ->get('/admin/institutional-settings')
                ->assertForbidden();
        }
    }

    public function test_form_mounts_with_current_singleton_data(): void
    {
        $setting = InstitutionalSetting::current();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageInstitutionalSettings::class)
            ->assertFormSet([
                'email' => $setting->email,
                'street' => $setting->street,
                'number' => $setting->number,
                'city' => $setting->city,
                'state' => $setting->state,
                'postal_code' => $setting->postal_code,
            ]);
    }

    public function test_editor_can_save_and_record_updates_in_database(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->conventionEditor);

        Livewire::test(ManageInstitutionalSettings::class)
            ->fillForm([
                'email' => 'novo-email@cbngo.org.br',
                'phone' => '(62) 3333-4444',
                'whatsapp' => '(62) 99999-8888',
                'office_hours' => 'Segunda a Sexta, das 09h às 18h',
                'street' => 'Avenida Goiás',
                'number' => '1000',
                'complement' => 'Sala 10',
                'district' => 'Centro',
                'city' => 'Goiânia',
                'state' => 'GO',
                'postal_code' => '74000-000',
                'maps_url' => 'https://maps.google.com/?q=Avenida+Goias',
                'instagram_url' => 'https://instagram.com/cbngoias',
                'facebook_url' => 'https://facebook.com/cbngoias',
                'youtube_url' => 'https://youtube.com/@cbngoias',
                'tiktok_url' => 'https://tiktok.com/@cbngoias',
                'spotify_url' => 'https://open.spotify.com/show/cbngoias',
                'linkedin_url' => 'https://linkedin.com/company/cbngoias',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $updated = InstitutionalSetting::current();
        $this->assertSame('novo-email@cbngo.org.br', $updated->email);
        $this->assertSame('(62) 3333-4444', $updated->phone);
        $this->assertSame('Avenida Goiás', $updated->street);
        $this->assertSame($this->conventionEditor->id, $updated->updated_by);
    }

    public function test_rejects_invalid_email(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageInstitutionalSettings::class)
            ->fillForm([
                'email' => 'email-invalido-sem-arroba',
            ])
            ->call('save')
            ->assertHasFormErrors(['email' => 'email']);
    }

    public function test_rejects_invalid_social_media_domains(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageInstitutionalSettings::class)
            ->fillForm([
                'email' => 'contato@cbngo.org.br',
                'instagram_url' => 'https://twitter.com/cbngoias',
                'facebook_url' => 'https://google.com/profile',
                'youtube_url' => 'https://vimeo.com/cbngoias',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'instagram_url' => 'regex',
                'facebook_url' => 'regex',
                'youtube_url' => 'regex',
            ]);
    }

    public function test_allows_empty_social_media_urls(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(ManageInstitutionalSettings::class)
            ->fillForm([
                'email' => 'contato@cbngo.org.br',
                'instagram_url' => null,
                'facebook_url' => null,
                'youtube_url' => null,
                'tiktok_url' => null,
                'spotify_url' => null,
                'linkedin_url' => null,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting = InstitutionalSetting::current();
        $this->assertNull($setting->instagram_url);
        $this->assertNull($setting->facebook_url);
    }

    public function test_seeder_is_idempotent_and_creates_exactly_one_record(): void
    {
        $this->seed(InstitutionalSettingSeeder::class);
        $this->assertSame(1, InstitutionalSetting::count());

        $this->seed(InstitutionalSettingSeeder::class);
        $this->assertSame(1, InstitutionalSetting::count());

        $setting = InstitutionalSetting::firstOrFail();
        $this->assertSame('contato@cbngo.org.br', $setting->email);
        $this->assertSame('Rua 04', $setting->street);
        $this->assertSame('515', $setting->number);
        $this->assertSame('74020-045', $setting->postal_code);
    }
}
