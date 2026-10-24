<?php

namespace App\Jobs;

use App\Support\MediaVariants;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Gera as versões WebP das imagens informadas. É despachado com
 * dispatchAfterResponse(), então roda logo após a resposta HTTP, sem
 * depender de um worker de fila na hospedagem compartilhada.
 */
class GenerateImageVariants
{
    use Dispatchable;

    /**
     * @param  list<string>  $paths
     */
    public function __construct(
        public array $paths,
        public bool $force = false,
    ) {}

    public function handle(): void
    {
        foreach ($this->paths as $path) {
            MediaVariants::generate($path, $this->force);
        }
    }
}
