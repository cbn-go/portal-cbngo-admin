<?php

namespace Tests\Feature\Models;

use App\Enums\NoticeValidityStatus;
use App\Enums\UserRole;
use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class BannerVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    public function test_visible_scope_respects_active_flag_and_period(): void
    {
        $openEnded = Banner::factory()->create();
        $inPeriod = Banner::factory()->create(['starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        $scheduled = Banner::factory()->scheduled()->create();
        $expired = Banner::factory()->expired()->create();
        $inactive = Banner::factory()->inactive()->create();

        $visible = Banner::query()->visible()->pluck('id')->all();

        $this->assertSame([$openEnded->id, $inPeriod->id], $visible);
        $this->assertNotContains($scheduled->id, $visible);
        $this->assertNotContains($expired->id, $visible);
        $this->assertNotContains($inactive->id, $visible);
    }

    public function test_visible_banners_are_ordered_by_position(): void
    {
        $third = Banner::factory()->create(['position' => 3]);
        $first = Banner::factory()->create(['position' => 1]);
        $second = Banner::factory()->create(['position' => 2]);

        $this->assertSame([$first->id, $second->id, $third->id], Banner::query()->visible()->pluck('id')->all());
    }

    public function test_banner_disappears_after_ends_at(): void
    {
        $banner = Banner::factory()->create(['ends_at' => now()->addHour()]);

        $this->assertTrue(Banner::query()->visible()->whereKey($banner->id)->exists());

        $this->travel(2)->hours();

        $this->assertFalse(Banner::query()->visible()->whereKey($banner->id)->exists());
        $this->assertSame(NoticeValidityStatus::EXPIRED, $banner->refresh()->validity_status);
    }

    public function test_validity_status_and_status_scopes(): void
    {
        $active = Banner::factory()->create();
        $scheduled = Banner::factory()->scheduled()->create();
        $expired = Banner::factory()->expired()->create();
        $inactive = Banner::factory()->inactive()->expired()->create();

        $this->assertSame(NoticeValidityStatus::ACTIVE, $active->validity_status);
        $this->assertSame(NoticeValidityStatus::SCHEDULED, $scheduled->validity_status);
        $this->assertSame(NoticeValidityStatus::EXPIRED, $expired->validity_status);
        $this->assertSame(NoticeValidityStatus::INACTIVE, $inactive->validity_status);

        $this->assertSame([$scheduled->id], Banner::query()->scheduled()->pluck('id')->all());
        $this->assertSame([$expired->id], Banner::query()->expired()->pluck('id')->all());
        $this->assertSame([$inactive->id], Banner::query()->inactive()->pluck('id')->all());
    }

    public function test_creation_fills_author_and_appends_position(): void
    {
        $editor = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);
        $this->actingAs($editor);

        $first = Banner::factory()->create();
        $second = Banner::factory()->create();

        $this->assertSame($editor->id, $first->user_id);
        $this->assertSame(1, $first->position);
        $this->assertSame(2, $second->position);
    }

    public function test_link_label_defaults(): void
    {
        $this->assertSame('Saiba mais', Banner::factory()->make(['link_label' => null])->display_link_label);
        $this->assertSame('Inscreva-se', Banner::factory()->make(['link_label' => 'Inscreva-se'])->display_link_label);
    }

    public function test_legacy_banner_without_square_photo_is_not_visible(): void
    {
        $squareBanner = Banner::factory()->create(['is_square' => true, 'is_active' => true]);
        $legacyBanner = Banner::factory()->create(['is_square' => false, 'is_active' => true]);

        $visibleIds = Banner::query()->visible()->pluck('id')->all();

        $this->assertContains($squareBanner->id, $visibleIds);
        $this->assertNotContains($legacyBanner->id, $visibleIds);
    }
}
