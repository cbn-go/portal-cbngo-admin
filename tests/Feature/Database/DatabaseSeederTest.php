<?php

namespace Tests\Feature\Database;

use App\Enums\PublishStatus;
use App\Enums\UserRole;
use App\Models\Article;
use App\Models\AuthorProfile;
use App\Models\Banner;
use App\Models\BoardMember;
use App\Models\Church;
use App\Models\Document;
use App\Models\News;
use App\Models\Notice;
use App\Models\User;
use Database\Seeders\ChurchSeeder;
use Database\Seeders\DevelopmentContentSeeder;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_super_admin_and_polo_churches(): void
    {
        $this->seed();

        $admin = User::query()->where('email', SuperAdminSeeder::EMAIL)->first();

        $this->assertInstanceOf(User::class, $admin);
        $this->assertSame(SuperAdminSeeder::NAME, $admin->name);
        $this->assertSame(UserRole::SUPER_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertNull($admin->church_id);

        $cities = [
            'Goiânia',
            'Aparecida de Goiânia',
            'Anápolis',
            'Rio Verde',
            'Jataí',
            'Catalão',
            'Itumbiara',
            'Luziânia',
        ];

        foreach ($cities as $city) {
            $this->assertDatabaseHas('churches', [
                'city' => $city,
                'state' => 'GO',
                'is_active' => true,
            ]);
        }

        $this->assertSame(count($cities), Church::query()->whereIn('city', $cities)->count());
    }

    public function test_polo_churches_have_public_portal_fields(): void
    {
        $this->seed();

        $church = Church::query()->where('name', 'Igreja Batista Nacional de Goiânia')->firstOrFail();

        $this->assertSame('igreja-batista-nacional-de-goiania', $church->slug);
        $this->assertSame('-16.6868912', $church->latitude);
        $this->assertSame('-49.2647943', $church->longitude);
        $this->assertSame(ChurchSeeder::DEFAULT_SERVICE_TIMES, $church->service_times);
    }

    public function test_development_content_is_seeded_for_public_portal_tables(): void
    {
        $this->seed();

        $this->assertSame(count(DevelopmentContentSeeder::AUTHORS), AuthorProfile::query()->count());
        $this->assertTrue(AuthorProfile::query()->where('slug', 'marcos-vinicius-andrade')->exists());

        $this->assertGreaterThan(0, Article::query()->where('status', PublishStatus::PUBLISHED)->count());
        $this->assertTrue(Article::query()->where('status', PublishStatus::DRAFT)->exists());
        $this->assertTrue(Article::query()->where('status', PublishStatus::PENDING_REVIEW)->exists());

        $this->assertTrue(News::query()->whereNull('church_id')->where('is_official', true)->exists());
        $this->assertTrue(News::query()->whereNotNull('church_id')->exists());
        $this->assertTrue(News::query()->where('status', PublishStatus::DRAFT)->exists());

        $this->assertSame(1, Notice::query()->currentlyActive()->count());
        $this->assertSame(1, Notice::query()->scheduled()->count());
        $this->assertSame(1, Notice::query()->expired()->count());

        $this->assertSame(14, BoardMember::query()->active()->count());
        $this->assertSame(4, BoardMember::query()->where('group', 'coordenacao_regional')->whereNotNull('region')->count());
        $this->assertSame('PDF', Document::query()->where('slug', 'estatuto-da-cbn')->value('file_format'));
        $this->assertSame('Página', Document::query()->where('slug', 'pacto-renovado')->value('file_format'));

        $this->assertSame(2, Banner::query()->visible()->count());
        $this->assertSame(1, Banner::query()->scheduled()->count());
        $this->assertSame(1, Banner::query()->expired()->count());
    }

    public function test_seeders_are_idempotent(): void
    {
        $this->seed();
        $this->seed();

        $this->assertSame(count(ChurchSeeder::POLO_CITIES), Church::query()->count());
        $this->assertSame(count(DevelopmentContentSeeder::AUTHORS), AuthorProfile::query()->count());
        $this->assertSame(6, Article::query()->count());
        $this->assertSame(6, News::query()->count());
        $this->assertSame(3, Notice::query()->count());
        $this->assertSame(14, BoardMember::query()->count());
        $this->assertSame(3, Document::query()->count());
        $this->assertSame(4, Banner::query()->count());
    }
}
