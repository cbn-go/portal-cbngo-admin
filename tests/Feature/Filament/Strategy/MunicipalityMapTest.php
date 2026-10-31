<?php

namespace Tests\Feature\Filament\Strategy;

use App\Enums\UserRole;
use App\Filament\Strategy\Widgets\MunicipalityMapWidget;
use App\Models\Church;
use App\Models\User;
use App\Services\Strategy\MunicipalityMap;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use Tests\TestCase;

class MunicipalityMapTest extends TestCase
{
    use CreatesStrategyData;
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = sys_get_temp_dir().'/cbngo-map-test-'.uniqid().'/goias-municipios.geojson';
        config()->set('strategy.map_path', $this->path);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(dirname($this->path));

        parent::tearDown();
    }

    private function writeMesh(): void
    {
        File::ensureDirectoryExists(dirname($this->path));
        File::put($this->path, (string) json_encode(['type' => 'FeatureCollection', 'features' => []]));
    }

    private function admin(UserRole $role = UserRole::SUPER_ADMIN, bool $active = true): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => $active]);
    }

    public function test_municipality_values_come_from_coverage_and_keep_missing_data_as_null(): void
    {
        $withChurch = $this->municipality(['ibge_code' => '5200001'], ['population_estimate' => 10000, 'religion_evangelical_pct_2022' => 30]);
        $this->municipality(['ibge_code' => '5200002']);
        Church::factory()->count(2)->create(['municipality_id' => $withChurch->id, 'is_active' => true]);

        $data = app(MunicipalityMap::class)->municipalities();

        $this->assertSame(10000, $data[5200001]['population']);
        $this->assertSame(30.0, $data[5200001]['evangelical_pct']);
        $this->assertSame(5000.0, $data[5200001]['inhabitants_per_church']);
        $this->assertSame(2, $data[5200001]['churches']);

        $this->assertNull($data[5200002]['population']);
        $this->assertNull($data[5200002]['evangelical_pct']);
        $this->assertNull($data[5200002]['inhabitants_per_church']);
        $this->assertSame(0, $data[5200002]['churches']);
    }

    public function test_quantile_classes_split_values_and_label_the_bounds(): void
    {
        $classes = app(MunicipalityMap::class)->quantileClasses(collect(range(1, 100))->map(fn (int $v): float => (float) $v), fn (float $v): string => (string) $v);

        $this->assertCount(5, $classes);
        $this->assertSame(MunicipalityMap::RAMP, array_column($classes, 'color'));
        $this->assertNull($classes[0]['min']);
        $this->assertNull($classes[4]['max']);
        $this->assertSame('Menos de 20', $classes[0]['label']);
        $this->assertSame('80 ou mais', $classes[4]['label']);
    }

    public function test_quantile_classes_merge_repeated_breaks(): void
    {
        $map = app(MunicipalityMap::class);

        $this->assertSame([], $map->quantileClasses(collect(), fn (float $v): string => (string) $v));

        $classes = $map->quantileClasses(collect([5.0, 5.0, 5.0, 5.0, 9.0]), fn (float $v): string => (string) $v);
        $this->assertCount(1, $classes);
        $this->assertSame('Todos os municípios', $classes[0]['label']);
    }

    public function test_layers_give_no_church_and_no_data_their_own_colors(): void
    {
        $this->municipality([], ['population_estimate' => 1000]);

        $map = app(MunicipalityMap::class);
        $layers = $map->layers($map->municipalities());

        $this->assertSame(['inhabitants_per_church', 'priority', 'population', 'evangelical_pct'], array_keys($layers));
        $this->assertSame(['no_church', 'no_data'], array_column($layers['inhabitants_per_church']['special'], 'key'));
        $this->assertNotContains(MunicipalityMap::NO_CHURCH_COLOR, MunicipalityMap::RAMP);
        $this->assertNotContains(MunicipalityMap::NO_DATA_COLOR, MunicipalityMap::RAMP);
    }

    public function test_only_active_churches_with_coordinates_become_markers(): void
    {
        $municipality = $this->municipality();
        Church::factory()->create(['municipality_id' => $municipality->id, 'name' => 'Com coordenadas', 'is_active' => true, 'latitude' => -16.68, 'longitude' => -49.25]);
        Church::factory()->create(['municipality_id' => $municipality->id, 'name' => 'Inativa', 'is_active' => false, 'latitude' => -16.7, 'longitude' => -49.3]);
        Church::factory()->create(['municipality_id' => $municipality->id, 'name' => 'Sem coordenadas', 'is_active' => true, 'latitude' => null, 'longitude' => null]);

        $map = app(MunicipalityMap::class);

        $this->assertSame(['Com coordenadas'], array_column($map->churches(), 'name'));
        $this->assertSame(1, $map->churchesWithoutCoordinates());
    }

    public function test_geojson_route_serves_the_mesh_to_admins_only(): void
    {
        $this->writeMesh();
        $url = route('filament.admin.strategy.map-geojson');

        foreach ([UserRole::SUPER_ADMIN, UserRole::CONVENTION_EDITOR] as $role) {
            $response = $this->actingAs($this->admin($role))->get($url);

            $response->assertOk()->assertHeader('Content-Type', 'application/geo+json');
            $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        }

        foreach ([
            $this->admin(UserRole::AUTHOR),
            $this->admin(UserRole::CHURCH_REPRESENTATIVE),
            $this->admin(UserRole::SUPER_ADMIN, active: false),
        ] as $user) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }
    }

    public function test_geojson_route_supports_etag_and_reports_a_missing_mesh(): void
    {
        $url = route('filament.admin.strategy.map-geojson');
        $this->actingAs($this->admin());

        $this->get($url)->assertNotFound();

        $this->writeMesh();
        $etag = $this->get($url)->headers->get('ETag');

        $this->assertNotNull($etag);
        $this->get($url, ['If-None-Match' => $etag])->assertStatus(304);
    }

    public function test_widget_renders_the_map_or_a_warning_when_the_mesh_is_missing(): void
    {
        $this->actingAs($this->admin());
        $this->municipality(['ibge_code' => '5208707', 'name' => 'Goiânia'], ['population_estimate' => 1500000]);

        Livewire::test(MunicipalityMapWidget::class)
            ->assertSee('Malha municipal ainda não sincronizada')
            ->assertDontSee('strategyMap(');

        $this->writeMesh();

        Livewire::test(MunicipalityMapWidget::class)
            ->assertDontSee('Malha municipal ainda não sincronizada')
            ->assertSee('strategyMap(', false)
            ->assertSee('malha.geojson', false)
            ->assertSee('Habitantes por igreja')
            ->assertSee('5208707', false);
    }
}
