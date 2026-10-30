<?php

namespace Tests\Feature\Media;

use App\Support\MediaVariants;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaVariantsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media');
    }

    private function putImage(string $path, int $width, int $height): void
    {
        Storage::disk('media')->put($path, UploadedFile::fake()->image('original.jpg', $width, $height)->getContent());
    }

    public function test_media_disk_is_a_public_local_disk(): void
    {
        $this->assertSame('media', MediaVariants::diskName());
        $this->assertSame('local', config('filesystems.disks.media.driver'));
        $this->assertSame('public', config('filesystems.disks.media.visibility'));
    }

    public function test_variant_paths_follow_naming_convention(): void
    {
        $this->assertSame(
            [
                'thumb' => 'news/featured/abc-thumb.webp',
                'card' => 'news/featured/abc-card.webp',
                'cover' => 'news/featured/abc-cover.webp',
            ],
            MediaVariants::pathsFor('news/featured/abc.jpg'),
        );

        $this->assertSame('logo-card.webp', MediaVariants::pathFor('logo.png', 'card'));
    }

    public function test_generates_webp_variants_with_expected_widths(): void
    {
        $this->putImage('news/featured/abc.jpg', 2000, 1000);

        $this->assertSame(3, MediaVariants::generate('news/featured/abc.jpg'));

        foreach (['thumb' => 400, 'card' => 800, 'cover' => 1600] as $variant => $width) {
            $path = "news/featured/abc-{$variant}.webp";
            Storage::disk('media')->assertExists($path);

            $info = getimagesizefromstring((string) Storage::disk('media')->get($path));
            $this->assertIsArray($info);
            $this->assertSame('image/webp', $info['mime']);
            $this->assertSame($width, $info[0]);
            $this->assertSame($width / 2, $info[1]);
        }

        Storage::disk('media')->assertExists('news/featured/abc.jpg');
    }

    public function test_small_images_are_not_upscaled(): void
    {
        $this->putImage('board/photos/small.jpg', 600, 600);

        MediaVariants::generate('board/photos/small.jpg');

        $widths = [];
        foreach (MediaVariants::pathsFor('board/photos/small.jpg') as $variant => $path) {
            $info = getimagesizefromstring((string) Storage::disk('media')->get($path));
            $this->assertIsArray($info);
            $widths[$variant] = $info[0];
        }

        $this->assertSame(['thumb' => 400, 'card' => 600, 'cover' => 600], $widths);
    }

    public function test_existing_variants_are_kept_unless_forced(): void
    {
        $this->putImage('churches/logos/logo.png', 900, 900);

        $this->assertSame(3, MediaVariants::generate('churches/logos/logo.png'));
        $this->assertSame(0, MediaVariants::generate('churches/logos/logo.png'));
        $this->assertSame(3, MediaVariants::generate('churches/logos/logo.png', force: true));
    }

    public function test_missing_or_invalid_files_are_ignored(): void
    {
        Storage::disk('media')->put('documents/files/not-an-image.jpg', 'texto');

        $this->assertSame(0, MediaVariants::generate('nao/existe.jpg'));
        $this->assertSame(0, MediaVariants::generate('documents/files/not-an-image.jpg'));
        Storage::disk('media')->assertMissing('documents/files/not-an-image-card.webp');
    }

    public function test_delete_removes_original_and_variants(): void
    {
        $this->putImage('articles/covers/a.jpg', 1200, 675);
        MediaVariants::generate('articles/covers/a.jpg');

        MediaVariants::deleteWithVariants('articles/covers/a.jpg');

        Storage::disk('media')->assertMissing('articles/covers/a.jpg');
        foreach (MediaVariants::pathsFor('articles/covers/a.jpg') as $path) {
            Storage::disk('media')->assertMissing($path);
        }
    }

    public function test_banner_images_generate_square_variants_with_160_800_1080_widths(): void
    {
        $this->putImage('banners/hero.jpg', 1080, 1080);

        $this->assertSame(
            [
                'thumb' => 'banners/hero-thumb.webp',
                'card' => 'banners/hero-card.webp',
                'cover' => 'banners/hero-cover.webp',
            ],
            MediaVariants::pathsFor('banners/hero.jpg'),
        );

        $this->assertSame(3, MediaVariants::generate('banners/hero.jpg'));

        foreach (['thumb' => 160, 'card' => 800, 'cover' => 1080] as $variant => $dimension) {
            $path = "banners/hero-{$variant}.webp";
            Storage::disk('media')->assertExists($path);

            $info = getimagesizefromstring((string) Storage::disk('media')->get($path));
            $this->assertIsArray($info);
            $this->assertSame('image/webp', $info['mime']);
            $this->assertSame($dimension, $info[0]);
            $this->assertSame($dimension, $info[1]);
        }
    }
}
