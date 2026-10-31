<?php

namespace Tests\Feature\Database;

use App\Models\Church;
use App\Models\Municipality;
use App\Services\Strategy\ChurchCoverageService;
use Database\Seeders\MunicipalitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipalitySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_links_churches_created_before_the_territorial_base(): void
    {
        // Como em produção: a igreja existia antes dos municípios e ficou sem vínculo.
        $church = Church::factory()->create(['city' => 'Guapó', 'is_active' => true]);
        $this->assertNull($church->fresh()?->municipality_id);

        $this->seed(MunicipalitySeeder::class);

        $guapo = Municipality::query()->where('name', 'Guapó')->firstOrFail();
        $this->assertSame($guapo->id, $church->fresh()?->municipality_id);

        $state = app(ChurchCoverageService::class)->getStateCoverage();
        $this->assertSame(1, $state->municipalitiesWithChurchCount);
        $this->assertSame(246, $state->totalMunicipalitiesCount);
    }

    public function test_keeps_existing_links_and_leaves_unknown_cities_unlinked(): void
    {
        $this->seed(MunicipalitySeeder::class);

        $anapolis = Municipality::query()->where('name', 'Anápolis')->firstOrFail();
        // Município escolhido à mão no cadastro: o seeder não sobrescreve.
        $chosen = Church::factory()->create(['municipality_id' => $anapolis->id, 'is_active' => true]);
        $chosen->updateQuietly(['city' => 'Goiânia']);

        $outside = Church::factory()->create(['city' => 'Brasília', 'is_active' => true]);

        $this->seed(MunicipalitySeeder::class);
        $this->seed(MunicipalitySeeder::class);

        $this->assertSame($anapolis->id, $chosen->fresh()?->municipality_id);
        $this->assertNull($outside->fresh()?->municipality_id);
        $this->assertSame(246, Municipality::query()->count());
    }

    public function test_link_reports_linked_and_unmatched_churches(): void
    {
        $this->seed(MunicipalitySeeder::class);
        Church::query()->delete();

        $match = Church::factory()->create(['city' => 'sao luis de montes belos']);
        $match->updateQuietly(['municipality_id' => null]);
        $miss = Church::factory()->create(['city' => 'Cidade Inexistente']);

        $result = Church::linkToMunicipalities();

        $this->assertSame(1, $result['linked']);
        $this->assertSame([$miss->id], array_map(fn (Church $church): int => $church->id, $result['unmatched']));
        $this->assertSame('São Luís de Montes Belos', $match->fresh()?->city);
    }
}
