<?php

namespace Tests\Feature\Filament;

use App\Enums\UserRole;
use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Models\Banner;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BannerResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Storage::fake('media');

        $this->superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $this->editor = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validData(array $overrides = []): array
    {
        return [
            'title' => 'Assembleia Geral Ordinária 2026',
            'tag' => 'Assembleia',
            'subtitle' => 'Dias 14 e 15 de março, em Anápolis. Inscreva os mensageiros da sua igreja até 28 de fevereiro.',
            'meta' => '14–15 mar · Anápolis',
            'image' => UploadedFile::fake()->image('aviso.jpg', 1080, 1080),
            'alt_text' => 'Pastores e líderes reunidos no auditório da assembleia em Anápolis.',
            'link_label' => 'Fazer inscrição',
            'link_url' => '/eventos/assembleia',
            'is_active' => true,
            ...$overrides,
        ];
    }

    public function test_admin_panel_roles_can_access_banner_pages(): void
    {
        $banner = Banner::factory()->create();

        foreach ([$this->superAdmin, $this->editor] as $user) {
            $this->actingAs($user)->get('/admin/banners')->assertSuccessful();
            $this->actingAs($user)->get('/admin/banners/create')->assertSuccessful();
            $this->actingAs($user)->get("/admin/banners/{$banner->id}/edit")->assertSuccessful();
        }
    }

    public function test_other_roles_and_inactive_users_cannot_access_banners(): void
    {
        $banner = Banner::factory()->create();

        $users = [
            User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'is_active' => true]),
            User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => false]),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)->get('/admin/banners')->assertForbidden();
            $this->actingAs($user)->get("/admin/banners/{$banner->id}/edit")->assertForbidden();
        }
    }

    public function test_editor_can_create_home_notice_with_1x1_photo_and_all_fields(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->editor);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData())
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::query()->where('title', 'Assembleia Geral Ordinária 2026')->firstOrFail();

        $this->assertStringStartsWith('banners/', $banner->image);
        Storage::disk('media')->assertExists($banner->image);
        $this->assertSame($this->editor->id, $banner->user_id);
        $this->assertSame('Assembleia', $banner->tag);
        $this->assertSame('Dias 14 e 15 de março, em Anápolis. Inscreva os mensageiros da sua igreja até 28 de fevereiro.', $banner->subtitle);
        $this->assertSame('14–15 mar · Anápolis', $banner->meta);
        $this->assertSame('Fazer inscrição', $banner->link_label);
        $this->assertSame('/eventos/assembleia', $banner->link_url);
        $this->assertFalse($banner->opens_in_new_tab);
        $this->assertSame(1, $banner->position);
        $this->assertTrue($banner->is_square);
    }

    public function test_form_has_all_1x1_sections_and_fields(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->assertFormFieldExists('title', fn (TextInput $field): bool => $field->getLabel() === 'Título do aviso' && $field->getMaxLength() === 70)
            ->assertFormFieldExists('tag', fn (TextInput $field): bool => $field->getLabel() === 'Selo de categoria' && $field->getMaxLength() === 30)
            ->assertFormFieldExists('subtitle', fn (Textarea $field): bool => $field->getLabel() === 'Subtítulo' && $field->getMaxLength() === 160)
            ->assertFormFieldExists('meta', fn (TextInput $field): bool => $field->getLabel() === 'Linha de apoio (data e local)' && $field->getMaxLength() === 60)
            ->assertFormFieldExists('link_label', fn (TextInput $field): bool => $field->getLabel() === 'Texto do botão')
            ->assertFormFieldExists('alt_text', fn (Textarea $field): bool => str_contains((string) $field->getHelperText(), 'leitores de tela'))
            ->assertFormFieldExists('image', fn (FileUpload $field): bool => $field->hasImageEditor()
                && str_contains((string) $field->getHelperText(), 'quadrada (1080×1080, mínimo 800×800)'));
    }

    public function test_rejects_non_square_images(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData(['image' => UploadedFile::fake()->image('widescreen.jpg', 1920, 1080)]))
            ->call('create')
            ->assertHasFormErrors(['image']);

        $this->assertSame(0, Banner::query()->count());
    }

    public function test_rejects_small_images_below_800x800(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData(['image' => UploadedFile::fake()->image('pequena.jpg', 600, 600)]))
            ->call('create')
            ->assertHasFormErrors(['image']);

        $this->assertSame(0, Banner::query()->count());
    }

    public function test_rejects_title_exceeding_70_characters(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData(['title' => str_repeat('A', 71)]))
            ->call('create')
            ->assertHasFormErrors(['title']);
    }

    public function test_rejects_subtitle_exceeding_160_characters(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData(['subtitle' => str_repeat('B', 161)]))
            ->call('create')
            ->assertHasFormErrors(['subtitle']);
    }

    public function test_title_image_and_alt_text_are_required(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->fillForm(['title' => null, 'image' => null, 'alt_text' => null])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required', 'image' => 'required', 'alt_text' => 'required']);
    }

    public function test_link_url_must_be_site_path_or_http_url(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        foreach (['eventos', 'javascript:alert(1)', '//example.com'] as $invalid) {
            Livewire::test(CreateBanner::class)
                ->fillForm($this->validData(['link_url' => $invalid]))
                ->call('create')
                ->assertHasFormErrors(['link_url']);
        }

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData(['link_url' => 'https://cbngo.org.br/noticias']))
            ->call('create')
            ->assertHasNoFormErrors();
    }

    public function test_external_link_turns_on_new_tab_and_internal_turns_it_off(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->fillForm(['link_url' => 'https://www.convencaobatistanacional.com.br'])
            ->assertFormSet(['opens_in_new_tab' => true])
            ->fillForm(['link_url' => 'https://cbngo.com.br/edital'])
            ->assertFormSet(['opens_in_new_tab' => false])
            ->fillForm(['link_url' => '/igrejas'])
            ->assertFormSet(['opens_in_new_tab' => false]);
    }

    public function test_ends_at_cannot_be_before_starts_at(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData([
                'starts_at' => now()->addDays(10)->format('Y-m-d H:i'),
                'ends_at' => now()->addDays(5)->format('Y-m-d H:i'),
            ]))
            ->call('create')
            ->assertHasFormErrors(['ends_at']);
    }

    public function test_super_admin_can_update_banner_period_and_link(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Storage::disk('media')->put('banners/existente.jpg', UploadedFile::fake()->image('e.jpg', 1920, 1080)->getContent());
        $banner = Banner::factory()->create(['image' => 'banners/existente.jpg', 'link_url' => null]);

        Livewire::test(EditBanner::class, ['record' => $banner->getKey()])
            ->fillForm([
                'link_url' => 'https://www.convencaobatistanacional.com.br',
                'ends_at' => now()->addWeek()->format('Y-m-d H:i'),
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $banner->refresh();
        $this->assertSame('https://www.convencaobatistanacional.com.br', $banner->link_url);
        $this->assertTrue($banner->opens_in_new_tab);
        $this->assertNotNull($banner->ends_at);
    }

    public function test_table_reorders_and_filters_by_status(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        $active = Banner::factory()->create();
        $scheduled = Banner::factory()->scheduled()->create();
        $expired = Banner::factory()->expired()->create();
        $inactive = Banner::factory()->inactive()->create();

        Livewire::test(ListBanners::class)
            ->assertCanSeeTableRecords([$active, $scheduled, $expired, $inactive], inOrder: true)
            ->assertCanRenderTableColumn('image')
            ->assertCanRenderTableColumn('validity_status');

        $filters = [
            'active' => $active,
            'scheduled' => $scheduled,
            'expired' => $expired,
            'inactive' => $inactive,
        ];

        foreach ($filters as $value => $expected) {
            Livewire::test(ListBanners::class)
                ->filterTable('validity', $value)
                ->assertCanSeeTableRecords([$expected])
                ->assertCountTableRecords(1);
        }

        Livewire::test(ListBanners::class)
            ->call('reorderTable', [(string) $expired->id, (string) $active->id, (string) $scheduled->id, (string) $inactive->id]);

        $this->assertSame(1, $expired->refresh()->position);
        $this->assertSame(2, $active->refresh()->position);
    }

    public function test_list_displays_legacy_banner_alert_when_16_9_banners_exist(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Banner::factory()->create(['is_square' => false, 'title' => 'Banner Antigo 16:9']);

        Livewire::test(ListBanners::class)
            ->assertSee('precisa de foto quadrada 1:1');

        $this->get('/admin/banners')
            ->assertSuccessful()
            ->assertSee('precisa de foto quadrada 1:1');
    }
}
