<?php

namespace Tests\Feature\Media;

use App\Models\BoardMember;
use App\Models\Church;
use App\Models\Document;
use App\Support\MediaVariants;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MediaCommandsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Bus::fake();
        Storage::fake('public');
        Storage::fake('media');
    }

    private function image(): string
    {
        return UploadedFile::fake()->image('img.jpg', 900, 600)->getContent();
    }

    public function test_migrate_public_copies_referenced_files_and_attachments(): void
    {
        Storage::disk('public')->put('board/photos/a.jpg', $this->image());
        Storage::disk('public')->put('documents/files/estatuto.pdf', '%PDF-1.4');
        Storage::disk('public')->put('news/attachments/inline.png', $this->image());
        Storage::disk('public')->put('outros/nao-referenciado.jpg', $this->image());

        BoardMember::factory()->create(['photo' => 'board/photos/a.jpg']);
        Church::factory()->create(['logo' => 'churches/logos/sumiu.png']);
        Document::factory()->create(['file' => 'documents/files/estatuto.pdf']);

        $this->artisan('media:migrate-public')
            ->expectsOutputToContain('Não encontrado no disco public: churches/logos/sumiu.png')
            ->expectsOutputToContain('Copiados 3 arquivo(s); 1 não encontrado(s).')
            ->assertSuccessful();

        Storage::disk('media')->assertExists('board/photos/a.jpg');
        Storage::disk('media')->assertExists('documents/files/estatuto.pdf');
        Storage::disk('media')->assertExists('news/attachments/inline.png');
        Storage::disk('media')->assertMissing('outros/nao-referenciado.jpg');

        $this->artisan('media:migrate-public')
            ->expectsOutputToContain('Copiados 0 arquivo(s)')
            ->assertSuccessful();
    }

    public function test_migrate_public_dry_run_copies_nothing(): void
    {
        Storage::disk('public')->put('board/photos/a.jpg', $this->image());
        BoardMember::factory()->create(['photo' => 'board/photos/a.jpg']);

        $this->artisan('media:migrate-public', ['--dry-run' => true])
            ->expectsOutputToContain('[dry-run] Copiando board/photos/a.jpg')
            ->expectsOutputToContain('Seriam copiados 1 arquivo(s)')
            ->assertSuccessful();

        Storage::disk('media')->assertMissing('board/photos/a.jpg');
    }

    public function test_variants_command_generates_missing_variants(): void
    {
        Storage::disk('media')->put('board/photos/a.jpg', $this->image());
        Storage::disk('media')->put('churches/logos/b.png', $this->image());
        BoardMember::factory()->create(['photo' => 'board/photos/a.jpg']);
        Church::factory()->create(['logo' => 'churches/logos/b.png']);

        $this->artisan('media:variants')
            ->expectsOutputToContain('2 imagem(ns) verificada(s); 8 versão(ões) gravada(s).')
            ->assertSuccessful();

        foreach ([...MediaVariants::pathsFor('board/photos/a.jpg'), ...MediaVariants::pathsFor('churches/logos/b.png')] as $path) {
            Storage::disk('media')->assertExists($path);
        }

        $this->artisan('media:variants')
            ->expectsOutputToContain('2 imagem(ns) verificada(s); 0 versão(ões) gravada(s).')
            ->assertSuccessful();
    }
}
