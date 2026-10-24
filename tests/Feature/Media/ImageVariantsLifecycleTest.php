<?php

namespace Tests\Feature\Media;

use App\Enums\UserRole;
use App\Filament\Resources\BoardMemberResource\Pages\CreateBoardMember;
use App\Jobs\GenerateImageVariants;
use App\Models\BoardMember;
use App\Models\News;
use App\Models\User;
use App\Support\MediaVariants;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ImageVariantsLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    private function putImageWithVariants(string $path): void
    {
        Storage::disk('media')->put($path, UploadedFile::fake()->image('img.jpg', 900, 600)->getContent());
        MediaVariants::generate($path);
    }

    public function test_saving_a_new_image_dispatches_variant_generation_after_response(): void
    {
        Bus::fake();

        BoardMember::factory()->create(['photo' => 'board/photos/a.jpg']);

        Bus::assertDispatchedAfterResponse(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job): bool => $job->paths === ['board/photos/a.jpg'],
        );
    }

    public function test_saving_without_image_changes_dispatches_nothing(): void
    {
        $member = BoardMember::factory()->create(['photo' => 'board/photos/a.jpg']);

        Bus::fake();
        $member->update(['role' => 'Vogal']);

        Bus::assertNotDispatchedAfterResponse(GenerateImageVariants::class);
    }

    public function test_job_generates_variants(): void
    {
        Storage::disk('media')->put('board/photos/a.jpg', UploadedFile::fake()->image('a.jpg', 900, 900)->getContent());

        (new GenerateImageVariants(['board/photos/a.jpg']))->handle();

        foreach (MediaVariants::pathsFor('board/photos/a.jpg') as $path) {
            Storage::disk('media')->assertExists($path);
        }
    }

    public function test_replacing_an_image_removes_the_old_original_and_variants(): void
    {
        Bus::fake();
        $this->putImageWithVariants('board/photos/old.jpg');
        $member = BoardMember::factory()->create(['photo' => 'board/photos/old.jpg']);

        $member->update(['photo' => 'board/photos/new.jpg']);

        Storage::disk('media')->assertMissing('board/photos/old.jpg');
        Storage::disk('media')->assertMissing('board/photos/old-card.webp');
        Bus::assertDispatchedAfterResponse(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job): bool => $job->paths === ['board/photos/new.jpg'],
        );
    }

    public function test_deleting_a_record_removes_its_images(): void
    {
        Bus::fake();
        $this->putImageWithVariants('board/photos/a.jpg');
        $member = BoardMember::factory()->create(['photo' => 'board/photos/a.jpg']);

        $member->delete();

        Storage::disk('media')->assertMissing('board/photos/a.jpg');
        foreach (MediaVariants::pathsFor('board/photos/a.jpg') as $path) {
            Storage::disk('media')->assertMissing($path);
        }
    }

    public function test_gallery_items_are_tracked_individually(): void
    {
        Bus::fake();
        $this->putImageWithVariants('news/gallery/1.jpg');
        $this->putImageWithVariants('news/gallery/2.jpg');

        $news = News::factory()->create([
            'featured_image' => 'news/featured/capa.jpg',
            'gallery' => ['news/gallery/1.jpg', 'news/gallery/2.jpg'],
        ]);

        $this->assertSame(
            ['news/featured/capa.jpg', 'news/gallery/1.jpg', 'news/gallery/2.jpg'],
            $news->currentImagePaths(),
        );

        $news->update(['gallery' => ['news/gallery/2.jpg', 'news/gallery/3.jpg']]);

        Storage::disk('media')->assertMissing('news/gallery/1.jpg');
        Storage::disk('media')->assertExists('news/gallery/2.jpg');
        Storage::disk('media')->assertExists('news/gallery/2-card.webp');
        Bus::assertDispatchedAfterResponse(
            GenerateImageVariants::class,
            fn (GenerateImageVariants $job): bool => $job->paths === ['news/gallery/3.jpg'],
        );
    }

    public function test_panel_upload_is_stored_as_relative_path_on_media_disk(): void
    {
        Bus::fake();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        $this->actingAs(User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]));

        Livewire::test(CreateBoardMember::class)
            ->fillForm([
                'name' => 'Pr. Teste de Mídia',
                'role' => 'Presidente',
                'group' => 'presidencia',
                'photo' => UploadedFile::fake()->image('foto.jpg', 500, 500),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $photo = BoardMember::query()->where('name', 'Pr. Teste de Mídia')->value('photo');

        $this->assertIsString($photo);
        $this->assertStringStartsWith('board/photos/', $photo);
        $this->assertDoesNotMatchRegularExpression('#^(https?:)?//#', $photo);
        Storage::disk('media')->assertExists($photo);
        Bus::assertDispatchedAfterResponse(GenerateImageVariants::class);
    }
}
