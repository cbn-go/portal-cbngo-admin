<?php

namespace App\Console\Commands;

use App\Support\MediaInventory;
use App\Support\MediaVariants;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class MigratePublicMedia extends Command
{
    protected $signature = 'media:migrate-public
        {--dry-run : Apenas lista o que seria copiado}';

    protected $description = 'Copia para o disco de mídia os uploads que ainda estão no disco "public" do painel';

    public function handle(): int
    {
        $source = Storage::disk('public');
        $target = MediaVariants::disk();
        $dryRun = (bool) $this->option('dry-run');

        $paths = [...MediaInventory::imagePaths(), ...MediaInventory::documentPaths()];

        foreach (MediaInventory::ATTACHMENT_DIRECTORIES as $directory) {
            array_push($paths, ...$source->allFiles($directory));
        }

        $copied = 0;
        $missing = 0;

        foreach (array_unique($paths) as $path) {
            if ($target->exists($path)) {
                continue;
            }

            if (! $source->exists($path)) {
                $missing++;
                $this->warn("Não encontrado no disco public: {$path}");

                continue;
            }

            $copied++;
            $this->line(($dryRun ? '[dry-run] ' : '')."Copiando {$path}");

            if (! $dryRun) {
                $target->writeStream($path, $source->readStream($path), ['visibility' => 'public']);
            }
        }

        $this->info(($dryRun ? 'Seriam copiados' : 'Copiados')." {$copied} arquivo(s); {$missing} não encontrado(s).");

        return self::SUCCESS;
    }
}
