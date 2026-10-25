<?php

namespace Tests\Feature\Filament;

use App\Enums\BannerFocalPoint;
use App\Enums\UserRole;
use App\Filament\Resources\BannerResource\Pages\CreateBanner;
use App\Filament\Resources\BannerResource\Pages\EditBanner;
use App\Filament\Resources\BannerResource\Pages\ListBanners;
use App\Models\Banner;
use App\Models\User;
use Filament\Facades\Filament;
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
            'title' => 'Assembleia Estadual 2026',
            'subtitle' => 'Inscrições abertas',
            'image' => UploadedFile::fake()->image('banner.jpg', 1920, 800),
            'focal_point' => BannerFocalPoint::CENTER->value,
            'alt_text' => 'Plenário da assembleia',
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

    public function test_editor_can_create_banner_with_image_on_media_disk(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->editor);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData(['link_url' => '/eventos/assembleia', 'link_label' => 'Inscreva-se']))
            ->call('create')
            ->assertHasNoFormErrors();

        $banner = Banner::query()->where('title', 'Assembleia Estadual 2026')->firstOrFail();

        $this->assertStringStartsWith('banners/', $banner->image);
        Storage::disk('media')->assertExists($banner->image);
        $this->assertSame($this->editor->id, $banner->user_id);
        $this->assertSame('/eventos/assembleia', $banner->link_url);
        $this->assertFalse($banner->opens_in_new_tab);
        $this->assertSame(1, $banner->position);
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

    public function test_rejects_small_images_and_non_images(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs($this->superAdmin);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData(['image' => UploadedFile::fake()->image('pequena.jpg', 800, 400)]))
            ->call('create')
            ->assertHasFormErrors(['image']);

        Livewire::test(CreateBanner::class)
            ->fillForm($this->validData(['image' => UploadedFile::fake()->create('arte.pdf', 100, 'application/pdf')]))
            ->call('create')
            ->assertHasFormErrors(['image']);

        $this->assertSame(0, Banner::query()->count());
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

        Storage::disk('media')->put('banners/existente.jpg', UploadedFile::fake()->image('e.jpg', 1920, 800)->getContent());
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
}
