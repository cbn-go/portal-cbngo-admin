<?php

namespace Tests\Feature\Filament;

use App\Enums\ProjectCategory;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Filament\Resources\SocialProjectResource;
use App\Filament\Resources\SocialProjectResource\Pages\CreateSocialProject;
use App\Filament\Resources\SocialProjectResource\Pages\EditSocialProject;
use App\Filament\Resources\SocialProjectResource\Pages\ListSocialProjects;
use App\Models\Church;
use App\Models\SocialProject;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SocialProjectResourceTest extends TestCase
{
    use RefreshDatabase;

    private Church $goiania;

    private Church $anapolis;

    private User $superAdmin;

    private User $editor;

    private User $goianiaRep;

    private User $anapolisRep;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Storage::fake('media');

        $this->goiania = Church::factory()->create(['name' => 'IBN Goiânia', 'city' => 'Goiânia']);
        $this->anapolis = Church::factory()->create(['name' => 'IBN Anápolis', 'city' => 'Anápolis']);

        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $this->editor = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);
        $this->goianiaRep = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'church_id' => $this->goiania->id, 'is_active' => true]);
        $this->anapolisRep = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'church_id' => $this->anapolis->id, 'is_active' => true]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function formData(array $overrides = []): array
    {
        return [
            'title' => 'Sopa Solidária',
            'slug' => 'sopa-solidaria',
            'category' => ProjectCategory::SOCIAL->value,
            'summary' => 'Distribuição semanal de sopa para pessoas em situação de rua.',
            'description' => '<p>Toda quinta-feira, às 19h, no centro.</p>',
            'target_audience' => 'Pessoas em situação de rua',
            'beneficiaries_count' => 120,
            'started_at' => '2026-03-01',
            'contact_name' => 'Maria',
            'contact_phone' => '(62) 99999-1234',
            'contact_email' => 'sopa@ibngoiania.org.br',
            'how_to_help' => 'Voluntários e doação de legumes.',
            ...$overrides,
        ];
    }

    public function test_representative_accesses_portal_pages_but_not_other_church_projects(): void
    {
        $own = SocialProject::factory()->create(['church_id' => $this->goiania->id]);
        $other = SocialProject::factory()->create(['church_id' => $this->anapolis->id]);

        $this->actingAs($this->goianiaRep)->get('/portal/social-projects')->assertSuccessful();
        $this->actingAs($this->goianiaRep)->get('/portal/social-projects/create')->assertSuccessful();
        $this->actingAs($this->goianiaRep)->get("/portal/social-projects/{$own->id}/edit")->assertSuccessful();
        $this->actingAs($this->goianiaRep)->get("/portal/social-projects/{$other->id}/edit")->assertNotFound();
        $this->actingAs($this->goianiaRep)->get('/admin/social-projects')->assertForbidden();

        $this->actingAs($this->anapolisRep)->get("/portal/social-projects/{$other->id}/edit")->assertSuccessful();
        $this->actingAs($this->anapolisRep)->get("/portal/social-projects/{$own->id}/edit")->assertNotFound();
    }

    public function test_admins_access_admin_pages_and_authors_are_denied(): void
    {
        $project = SocialProject::factory()->create();

        foreach ([$this->superAdmin, $this->editor] as $admin) {
            $this->actingAs($admin)->get('/admin/social-projects')->assertSuccessful();
            $this->actingAs($admin)->get("/admin/social-projects/{$project->id}/edit")->assertSuccessful();
        }

        $author = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $this->actingAs($author)->get('/portal/social-projects')->assertForbidden();
    }

    public function test_representative_lists_only_own_church_projects(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $own = SocialProject::factory()->create(['church_id' => $this->goiania->id]);
        $other = SocialProject::factory()->create(['church_id' => $this->anapolis->id]);

        $this->actingAs($this->goianiaRep);

        Livewire::test(ListSocialProjects::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_representative_creates_project_for_own_church_and_submits_for_review(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->goianiaRep);

        Livewire::test(CreateSocialProject::class)
            ->assertFormFieldIsHidden('church_id')
            ->assertFormFieldIsHidden('is_featured')
            ->assertFormFieldIsHidden('published_at')
            ->assertFormSet(['city' => 'Goiânia'])
            ->fillForm($this->formData([
                'status' => PublishStatus::PENDING_REVIEW->value,
                'cover_image' => UploadedFile::fake()->image('capa.jpg', 1600, 900),
                'gallery' => [UploadedFile::fake()->image('foto1.jpg'), UploadedFile::fake()->image('foto2.jpg')],
            ]))
            ->call('create')
            ->assertHasNoFormErrors();

        $project = SocialProject::query()->where('slug', 'sopa-solidaria')->firstOrFail();

        $this->assertSame($this->goiania->id, $project->church_id);
        $this->assertSame($this->goianiaRep->id, $project->user_id);
        $this->assertSame('Goiânia', $project->city);
        $this->assertSame(PublishStatus::PENDING_REVIEW, $project->status);
        $this->assertNull($project->published_at);
        $this->assertStringStartsWith('projects/covers/', (string) $project->cover_image);
        Storage::disk('media')->assertExists((string) $project->cover_image);
        $this->assertCount(2, $project->gallery ?? []);
        $this->assertStringStartsWith('projects/galleries/', $project->gallery[0]);
    }

    public function test_representative_cannot_choose_published_status(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->goianiaRep);

        Livewire::test(CreateSocialProject::class)
            ->assertFormFieldExists('status', fn (Select $field): bool => array_keys($field->getOptions()) === ['draft', 'pending_review']);

        Livewire::test(CreateSocialProject::class)
            ->fillForm($this->formData(['status' => PublishStatus::PUBLISHED->value]))
            ->call('create')
            ->assertHasFormErrors(['status']);

        $this->assertSame(0, SocialProject::query()->count());
    }

    public function test_representative_editing_published_project_sends_it_back_to_review(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $project = SocialProject::factory()->published()->create(['church_id' => $this->goiania->id]);

        $this->actingAs($this->goianiaRep);

        Livewire::test(EditSocialProject::class, ['record' => $project->getKey()])
            ->assertFormSet(['status' => PublishStatus::PENDING_REVIEW->value])
            ->fillForm(['summary' => 'Agora também aos sábados.'])
            ->call('save')
            ->assertHasNoFormErrors();

        $project->refresh();
        $this->assertSame('Agora também aos sábados.', $project->summary);
        $this->assertSame(PublishStatus::PENDING_REVIEW, $project->status);
        $this->assertFalse(SocialProject::query()->published()->whereKey($project->id)->exists());
    }

    public function test_representative_sees_review_notes_and_has_no_admin_actions(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $project = SocialProject::factory()->create([
            'church_id' => $this->goiania->id,
            'review_notes' => 'Inclua o horário das atividades.',
        ]);
        $pending = SocialProject::factory()->pendingReview()->create(['church_id' => $this->goiania->id]);

        $this->actingAs($this->goianiaRep);

        Livewire::test(EditSocialProject::class, ['record' => $project->getKey()])
            ->assertSee('Inclua o horário das atividades.');

        Livewire::test(ListSocialProjects::class)
            ->assertTableActionHidden('approve', $pending)
            ->assertTableActionHidden('returnForAdjustments', $pending)
            ->assertTableActionHidden('toggleFeatured', $pending);
    }

    public function test_admin_approves_pending_project(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $project = SocialProject::factory()->pendingReview()->create(['review_notes' => 'antigo']);

        $this->actingAs($this->editor);

        Livewire::test(ListSocialProjects::class)
            ->assertTableActionVisible('approve', $project)
            ->callTableAction('approve', $project);

        $project->refresh();
        $this->assertSame(PublishStatus::PUBLISHED, $project->status);
        $this->assertNotNull($project->published_at);
        $this->assertNull($project->review_notes);
        $this->assertTrue(SocialProject::query()->published()->whereKey($project->id)->exists());
    }

    public function test_admin_returns_project_with_required_notes(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $project = SocialProject::factory()->pendingReview()->create();

        $this->actingAs($this->superAdmin);

        Livewire::test(EditSocialProject::class, ['record' => $project->getKey()])
            ->callAction('returnForAdjustments', ['review_notes' => ''])
            ->assertHasActionErrors(['review_notes' => 'required']);

        Livewire::test(EditSocialProject::class, ['record' => $project->getKey()])
            ->callAction('returnForAdjustments', ['review_notes' => 'Faltou a foto de capa.'])
            ->assertHasNoActionErrors();

        $project->refresh();
        $this->assertSame(PublishStatus::DRAFT, $project->status);
        $this->assertSame('Faltou a foto de capa.', $project->review_notes);
    }

    public function test_approve_actions_only_for_pending_projects(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $draft = SocialProject::factory()->create();

        $this->actingAs($this->superAdmin);

        Livewire::test(ListSocialProjects::class)
            ->assertTableActionHidden('approve', $draft)
            ->assertTableActionHidden('returnForAdjustments', $draft);
    }

    public function test_admin_toggles_featured(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $project = SocialProject::factory()->published()->create();

        $this->actingAs($this->superAdmin);

        Livewire::test(ListSocialProjects::class)->callTableAction('toggleFeatured', $project);
        $this->assertTrue($project->refresh()->is_featured);

        Livewire::test(ListSocialProjects::class)->callTableAction('toggleFeatured', $project);
        $this->assertFalse($project->refresh()->is_featured);
    }

    public function test_admin_filters_projects(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $social = SocialProject::factory()->published()->create(['church_id' => $this->goiania->id, 'city' => 'Goiânia', 'category' => ProjectCategory::SOCIAL]);
        $cultural = SocialProject::factory()->ended()->create(['church_id' => $this->anapolis->id, 'city' => 'Anápolis', 'category' => ProjectCategory::CULTURAL]);

        $cases = [
            ['category', ProjectCategory::CULTURAL->value, $cultural, $social],
            ['church_id', (string) $this->goiania->id, $social, $cultural],
            ['city', 'Anápolis', $cultural, $social],
            ['status', PublishStatus::PUBLISHED->value, $social, $cultural],
            ['ongoing', true, $social, $cultural],
            ['ongoing', false, $cultural, $social],
        ];

        foreach ($cases as [$filter, $value, $visible, $hidden]) {
            Livewire::test(ListSocialProjects::class)
                ->filterTable($filter, $value)
                ->assertCanSeeTableRecords([$visible])
                ->assertCanNotSeeTableRecords([$hidden]);
        }
    }

    public function test_navigation_badge_counts_pending_projects_for_admins_only(): void
    {
        SocialProject::factory()->pendingReview()->count(2)->create(['church_id' => $this->goiania->id]);
        SocialProject::factory()->create();

        $this->actingAs($this->superAdmin);
        $this->assertSame('2', SocialProjectResource::getNavigationBadge());

        $this->actingAs($this->goianiaRep);
        $this->assertNull(SocialProjectResource::getNavigationBadge());
    }

    public function test_admin_must_choose_church_and_city_is_filled_from_it(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateSocialProject::class)
            ->fillForm($this->formData(['status' => PublishStatus::PUBLISHED->value]))
            ->call('create')
            ->assertHasFormErrors(['church_id' => 'required']);

        Livewire::test(CreateSocialProject::class)
            ->fillForm(['church_id' => $this->anapolis->id])
            ->assertFormSet(['city' => 'Anápolis'])
            ->fillForm($this->formData(['status' => PublishStatus::PUBLISHED->value, 'city' => 'Anápolis']))
            ->call('create')
            ->assertHasNoFormErrors();

        $project = SocialProject::query()->where('slug', 'sopa-solidaria')->firstOrFail();
        $this->assertSame($this->anapolis->id, $project->church_id);
        $this->assertSame(PublishStatus::PUBLISHED, $project->status);
    }

    public function test_form_validation(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->goianiaRep);

        Livewire::test(CreateSocialProject::class)
            ->fillForm(['title' => null, 'slug' => null, 'category' => null, 'summary' => null, 'description' => null, 'started_at' => null, 'city' => null])
            ->call('create')
            ->assertHasFormErrors([
                'title' => 'required',
                'slug' => 'required',
                'category' => 'required',
                'summary' => 'required',
                'description' => 'required',
                'started_at' => 'required',
                'city' => 'required',
            ]);

        Livewire::test(CreateSocialProject::class)
            ->fillForm($this->formData([
                'ended_at' => '2026-01-01',
                'contact_email' => 'nao-e-email',
                'beneficiaries_count' => -5,
                'cover_image' => UploadedFile::fake()->create('capa.pdf', 100, 'application/pdf'),
            ]))
            ->call('create')
            ->assertHasFormErrors(['ended_at', 'contact_email', 'beneficiaries_count', 'cover_image']);

        SocialProject::factory()->create(['slug' => 'sopa-solidaria']);

        Livewire::test(CreateSocialProject::class)
            ->fillForm($this->formData())
            ->call('create')
            ->assertHasFormErrors(['slug' => 'unique']);
    }

    public function test_description_is_sanitized_when_saved_from_the_form(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('portal'));
        $this->actingAs($this->goianiaRep);

        Livewire::test(CreateSocialProject::class)
            ->fillForm($this->formData(['description' => '<p>Olá</p><script>alert("x")</script><a href="javascript:alert(1)">clique</a>']))
            ->call('create')
            ->assertHasNoFormErrors();

        $description = SocialProject::query()->where('slug', 'sopa-solidaria')->value('description');

        $this->assertIsString($description);
        $this->assertStringContainsString('<p>Olá</p>', $description);
        $this->assertStringNotContainsString('<script', $description);
        $this->assertStringNotContainsString('javascript:', $description);
    }
}
