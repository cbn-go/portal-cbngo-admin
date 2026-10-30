<?php

namespace App\Console\Commands;

use App\Support\MediaInventory;
use App\Support\MediaVariants;
use Illuminate\Console\Command;

class GenerateMediaVariants extends Command
{
    protected $signature = 'media:variants
        {--force : Regera também as versões que já existem}';

    protected $description = 'Gera as versões WebP (thumb, card, cover, full) de todas as imagens cadastradas';

    public function handle(): int
    {
        $force = (bool) $this->option('force');
        $paths = MediaInventory::imagePaths();
        $written = 0;

        $this->withProgressBar($paths, function (string $path) use ($force, &$written): void {
            $written += MediaVariants::generate($path, $force);
        });

        $this->newLine();
        $this->info(count($paths)." imagem(ns) verificada(s); {$written} versão(ões) gravada(s).");

        return self::SUCCESS;
    }
}
