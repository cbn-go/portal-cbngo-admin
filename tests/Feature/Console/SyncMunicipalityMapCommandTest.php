<?php

namespace Tests\Feature\Console;

use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMunicipalityMapCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        // Arquivo em pasta temporária: o teste nunca toca a malha real do ambiente.
        $this->path = sys_get_temp_dir().'/cbngo-map-test-'.uniqid().'/goias-municipios.geojson';
        config()->set('strategy.map_path', $this->path);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->path));

        parent::tearDown();
    }

    /**
     * @return array<string, mixed>
     */
    private function featureCollection(int $count, ?string $extraCode = null): array
    {
        $codes = Municipality::query()->orderBy('id')->limit($count)->pluck('ibge_code')->all();

        if ($extraCode !== null) {
            $codes[] = $extraCode;
        }

        return [
            'type' => 'FeatureCollection',
            'features' => array_map(fn (string $code): array => [
                'type' => 'Feature',
                'geometry' => ['type' => 'Polygon', 'coordinates' => [[[-49.0, -16.0], [-49.1, -16.0], [-49.1, -16.1], [-49.0, -16.0]]]],
                'properties' => ['codarea' => $code],
            ], $codes),
        ];
    }

    private function seedMunicipalities(): void
    {
        foreach (range(1, 246) as $i) {
            Municipality::factory()->create(['ibge_code' => '52'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)]);
        }
    }

    public function test_downloads_and_saves_a_valid_mesh(): void
    {
        $this->seedMunicipalities();
        Http::fake(['servicodados.ibge.gov.br/api/v3/malhas/*' => Http::response($this->featureCollection(246))]);

        $this->artisan('strategy:sync-map')->assertSuccessful();

        $saved = json_decode((string) File::get($this->path), true);
        $this->assertCount(246, $saved['features']);
        $this->assertFileDoesNotExist($this->path.'.tmp');

        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'intrarregiao=municipio')
            && str_contains($request->url(), 'qualidade=minima'));
    }

    public function test_incomplete_or_invalid_responses_keep_the_previous_file(): void
    {
        $this->seedMunicipalities();
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, 'malha anterior');

        foreach ([
            Http::response($this->featureCollection(100)),
            Http::response(['type' => 'Feature']),
            Http::response($this->featureCollection(245, '5399999')),
            Http::response('erro', 500),
        ] as $response) {
            Http::fake(['servicodados.ibge.gov.br/*' => $response]);

            $this->artisan('strategy:sync-map')->assertFailed();
            $this->assertSame('malha anterior', File::get($this->path));
        }
    }

    public function test_if_missing_skips_when_the_mesh_already_exists(): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, '{}');
        Http::fake();

        $this->artisan('strategy:sync-map', ['--if-missing' => true])->assertSuccessful();

        Http::assertNothingSent();
    }
}
