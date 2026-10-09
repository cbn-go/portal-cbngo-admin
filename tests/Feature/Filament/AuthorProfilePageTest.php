<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Portal\Pages\EditAuthorProfile;
use App\Models\AuthorProfile;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorProfilePageTest extends TestCase
{
    use RefreshDatabase;

    private User $author;

    protected function setUp(): void
    {
        parent::setUp();

        $this->author = User::factory()->create([
            'name' => 'Pastor João da Silva',
            'email' => 'joao.silva@cbngo.com.br',
            'role' => UserRole::AUTHOR,
            'is_active' => true,
        ]);
    }

    public function test_author_can_access_profile_page_on_portal_panel(): void
    {
        $this->actingAs($this->author)
            ->get('/portal/profile')
            ->assertSuccessful();
    }

    public function test_unauthenticated_user_cannot_access_profile_page(): void
    {
        $this->get('/portal/profile')
            ->assertRedirect('/portal/login');
    }

    public function test_author_can_create_and_update_author_profile_data(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        Livewire::test(EditAuthorProfile::class)
            ->fillForm([
                'name' => 'Pr. João Silva Atualizado',
                'email' => 'joao.silva@cbngo.com.br',
                'pastoral_title' => 'Pastor Presidente',
                'bio' => 'Ministério pastoral dedicado ao Reino e à Convenção Batista Nacional.',
                'social_instagram' => '@prjoaosilva',
                'social_facebook' => 'https://facebook.com/prjoaosilva',
                'social_youtube' => 'https://youtube.com/@prjoaosilva',
                'social_linkedin' => 'https://linkedin.com/in/prjoaosilva',
                'social_website' => 'https://joaosilva.com.br',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('users', [
            'id' => $this->author->id,
            'name' => 'Pr. João Silva Atualizado',
            'email' => 'joao.silva@cbngo.com.br',
        ]);

        $this->assertDatabaseHas('author_profiles', [
            'user_id' => $this->author->id,
            'pastoral_title' => 'Pastor Presidente',
            'bio' => 'Ministério pastoral dedicado ao Reino e à Convenção Batista Nacional.',
        ]);

        $profile = AuthorProfile::where('user_id', $this->author->id)->first();
        $this->assertNotNull($profile);
        $this->assertIsArray($profile->social_links);
        $this->assertSame('@prjoaosilva', $profile->social_links['instagram'] ?? null);
        $this->assertSame('https://facebook.com/prjoaosilva', $profile->social_links['facebook'] ?? null);
        $this->assertSame('https://youtube.com/@prjoaosilva', $profile->social_links['youtube'] ?? null);
        $this->assertSame('https://linkedin.com/in/prjoaosilva', $profile->social_links['linkedin'] ?? null);
        $this->assertSame('https://joaosilva.com.br', $profile->social_links['website'] ?? null);
    }

    public function test_profile_page_prefills_existing_author_profile_data(): void
    {
        AuthorProfile::factory()->create([
            'user_id' => $this->author->id,
            'pastoral_title' => 'Evangelista',
            'bio' => 'Evangelista e teólogo escritor.',
            'social_links' => [
                'instagram' => '@evangelista.joao',
                'website' => 'https://teologiaevangelica.com.br',
            ],
        ]);

        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        Livewire::test(EditAuthorProfile::class)
            ->assertFormSet([
                'name' => 'Pastor João da Silva',
                'email' => 'joao.silva@cbngo.com.br',
                'pastoral_title' => 'Evangelista',
                'bio' => 'Evangelista e teólogo escritor.',
                'social_instagram' => '@evangelista.joao',
                'social_website' => 'https://teologiaevangelica.com.br',
            ]);
    }

    public function test_profile_form_validates_required_name_and_email(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        Livewire::test(EditAuthorProfile::class)
            ->fillForm([
                'name' => null,
                'email' => null,
            ])
            ->call('save')
            ->assertHasFormErrors([
                'name' => 'required',
                'email' => 'required',
            ]);
    }

    public function test_non_author_roles_cannot_access_author_profile_page(): void
    {
        $churchRep = User::factory()->create([
            'role' => UserRole::CHURCH_REPRESENTATIVE,
            'is_active' => true,
        ]);

        $this->actingAs($churchRep)
            ->get('/portal/profile')
            ->assertForbidden();

        $superAdmin = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'is_active' => true,
        ]);

        $this->actingAs($superAdmin)
            ->get('/portal/profile')
            ->assertForbidden();
    }

    public function test_profile_form_validates_social_media_urls(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        Livewire::test(EditAuthorProfile::class)
            ->fillForm([
                'social_facebook' => 'invalid-facebook-url',
                'social_youtube' => 'invalid-youtube-url',
                'social_linkedin' => 'invalid-linkedin-url',
                'social_website' => 'invalid-website-url',
            ])
            ->call('save')
            ->assertHasFormErrors([
                'social_facebook' => 'url',
                'social_youtube' => 'url',
                'social_linkedin' => 'url',
                'social_website' => 'url',
            ]);
    }

    public function test_password_update_persists_without_double_hashing(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->author);

        Livewire::test(EditAuthorProfile::class)
            ->fillForm([
                'password' => 'NovaSenhaSegura123!',
                'password_confirmation' => 'NovaSenhaSegura123!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('NovaSenhaSegura123!', $this->author->fresh()->password));
    }
}
