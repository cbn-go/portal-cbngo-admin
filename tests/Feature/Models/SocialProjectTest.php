<?php

namespace Tests\Feature\Models;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Church;
use App\Models\SocialProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class SocialProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
    }

    public function test_published_scope_excludes_unpublished_and_future_projects(): void
    {
        $published = SocialProject::factory()->published()->create();
        SocialProject::factory()->create();
        SocialProject::factory()->pendingReview()->create();
        SocialProject::factory()->create(['status' => PublishStatus::ARCHIVED, 'published_at' => now()->subDay()]);
        SocialProject::factory()->create(['status' => PublishStatus::PUBLISHED, 'published_at' => now()->addDay()]);

        $this->assertSame([$published->id], SocialProject::query()->published()->pluck('id')->all());
    }

    public function test_publishing_without_date_fills_published_at(): void
    {
        $project = SocialProject::factory()->create(['status' => PublishStatus::PUBLISHED, 'published_at' => null]);

        $this->assertNotNull($project->published_at);
        $this->assertTrue(SocialProject::query()->published()->whereKey($project->id)->exists());
    }

    public function test_ongoing_and_ended_scopes(): void
    {
        $openEnded = SocialProject::factory()->create();
        $endsToday = SocialProject::factory()->create(['ended_at' => today()]);
        $ended = SocialProject::factory()->ended()->create();

        $this->assertEqualsCanonicalizing([$openEnded->id, $endsToday->id], SocialProject::query()->ongoing()->pluck('id')->all());
        $this->assertSame([$ended->id], SocialProject::query()->ended()->pluck('id')->all());
        $this->assertTrue($endsToday->isOngoing());
        $this->assertFalse($ended->isOngoing());
    }

    public function test_description_html_is_sanitized(): void
    {
        $project = SocialProject::factory()->create([
            'description' => '<p onclick="steal()">Texto <strong>seguro</strong></p><script>alert(1)</script><img src="x.jpg" onerror="hack()">',
        ]);

        $this->assertStringNotContainsString('<script', $project->description);
        $this->assertStringNotContainsString('onclick', $project->description);
        $this->assertStringNotContainsString('onerror', $project->description);
        $this->assertStringContainsString('<strong>seguro</strong>', $project->description);
    }

    public function test_slug_is_generated_and_unique(): void
    {
        $first = SocialProject::factory()->create(['title' => 'Sopa Solidária', 'slug' => null]);
        $second = SocialProject::factory()->create(['title' => 'Sopa Solidária', 'slug' => null]);
        $symbols = SocialProject::factory()->create(['title' => '!!!', 'slug' => null]);

        $this->assertSame('sopa-solidaria', $first->slug);
        $this->assertSame('sopa-solidaria-1', $second->slug);
        $this->assertSame('projeto', $symbols->slug);
    }

    public function test_city_defaults_to_church_city(): void
    {
        $church = Church::factory()->create(['city' => 'Jataí']);

        $project = SocialProject::factory()->create(['church_id' => $church->id, 'city' => null]);

        $this->assertSame('Jataí', $project->city);
    }

    public function test_approve_and_return_for_adjustments(): void
    {
        $project = SocialProject::factory()->pendingReview()->create(['review_notes' => 'Faltava a capa.']);

        $project->approve();

        $this->assertSame(PublishStatus::PUBLISHED, $project->status);
        $this->assertNotNull($project->published_at);
        $this->assertNull($project->review_notes);

        $other = SocialProject::factory()->pendingReview()->create();
        $other->returnForAdjustments('Inclua o horário das atividades.');

        $this->assertSame(PublishStatus::DRAFT, $other->refresh()->status);
        $this->assertSame('Inclua o horário das atividades.', $other->review_notes);
    }

    public function test_representative_cannot_publish_or_feature_directly(): void
    {
        $church = Church::factory()->create();
        $other = Church::factory()->create();
        $representative = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'church_id' => $church->id, 'is_active' => true]);
        $this->actingAs($representative);

        $project = SocialProject::factory()->create([
            'church_id' => $other->id,
            'status' => PublishStatus::PUBLISHED,
            'published_at' => now(),
            'is_featured' => true,
        ]);

        $this->assertSame($church->id, $project->church_id);
        $this->assertSame($representative->id, $project->user_id);
        $this->assertSame(PublishStatus::PENDING_REVIEW, $project->status);
        $this->assertNull($project->published_at);
        $this->assertFalse($project->is_featured);
    }

    public function test_representative_edit_of_published_project_returns_it_to_review(): void
    {
        $church = Church::factory()->create();
        $representative = User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'church_id' => $church->id, 'is_active' => true]);
        $project = SocialProject::factory()->published()->featured()->create(['church_id' => $church->id, 'review_notes' => null]);

        $this->actingAs($representative);
        $project->update([
            'summary' => 'Resumo atualizado pelo representante.',
            'is_featured' => false,
            'review_notes' => 'tentativa',
        ]);

        $project->refresh();
        $this->assertSame(PublishStatus::PENDING_REVIEW, $project->status);
        $this->assertNull($project->published_at);
        $this->assertTrue($project->is_featured);
        $this->assertNull($project->review_notes);
        $this->assertFalse(SocialProject::query()->withoutGlobalScopes()->published()->whereKey($project->id)->exists());
    }

    public function test_global_scope_isolates_churches_for_representatives(): void
    {
        $church = Church::factory()->create();
        $mine = SocialProject::factory()->create(['church_id' => $church->id]);
        SocialProject::factory()->create();

        $this->actingAs(User::factory()->create(['role' => UserRole::CHURCH_REPRESENTATIVE, 'church_id' => $church->id, 'is_active' => true]));

        $this->assertSame([$mine->id], SocialProject::query()->pluck('id')->all());
    }
}
