<?php

namespace Tests\Feature\Ormiban;

use App\Enums\UserRole;
use App\Filament\Ormiban\Pages\EditMyChurch;
use App\Filament\Resources\MunicipalityResource\Pages\ViewMunicipality;
use App\Models\Church;
use App\Models\Minister;
use App\Models\Municipality;
use App\Models\SocialProject;
use App\Models\User;
use App\Services\Strategy\MunicipalityMap;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\Feature\Filament\Strategy\CreatesStrategyData;
use Tests\TestCase;

class MinisterStrategyAccessTest extends TestCase
{
    use CreatesStrategyData;
    use RefreshDatabase;

    /**
     * @return list<string>
     */
    private function ormibanStrategyUrls(Municipality $municipality): array
    {
        return [
            '/ormiban/estrategia',
            '/ormiban/estrategia/municipios',
            "/ormiban/estrategia/municipios/{$municipality->id}",
            '/ormiban/estrategia/metodologia',
        ];
    }

    public function test_user_role_can_access_strategy_helper(): void
    {
        $this->assertTrue(UserRole::SUPER_ADMIN->canAccessStrategy());
        $this->assertTrue(UserRole::CONVENTION_EDITOR->canAccessStrategy());
        $this->assertTrue(UserRole::MINISTER->canAccessStrategy());
        $this->assertFalse(UserRole::AUTHOR->canAccessStrategy());
        $this->assertFalse(UserRole::CHURCH_REPRESENTATIVE->canAccessStrategy());
    }

    public function test_active_minister_can_access_all_strategy_screens_in_ormiban_panel(): void
    {
        $municipality = Municipality::factory()->create();
        $ministerUser = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        foreach ($this->ormibanStrategyUrls($municipality) as $url) {
            $this->actingAs($ministerUser)->get($url)->assertSuccessful();
        }
    }

    public function test_strategy_overview_opens_for_a_minister_when_a_church_has_coordinates(): void
    {
        $path = sys_get_temp_dir().'/cbngo-ormiban-map-'.uniqid().'/goias-municipios.geojson';
        File::ensureDirectoryExists(dirname($path));
        File::put($path, (string) json_encode(['type' => 'FeatureCollection', 'features' => []]));
        config()->set('strategy.map_path', $path);

        $church = Church::factory()->create([
            'name' => 'Igreja com coordenadas',
            'is_active' => true,
            'latitude' => -16.68,
            'longitude' => -49.25,
        ]);
        $ministerUser = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('ormiban'));

        $markers = app(MunicipalityMap::class)->churches();

        $this->assertNull($markers[0]['url']);
        $this->assertFalse(Route::has('filament.ormiban.resources.churches.edit'));

        $this->actingAs($ministerUser)
            ->get('/ormiban/estrategia')
            ->assertSuccessful()
            ->assertDontSee('filament.ormiban.resources.churches.edit')
            ->assertDontSee('/admin/churches/'.$church->id.'/edit');

        File::deleteDirectory(dirname($path));
    }

    public function test_active_minister_can_fetch_strategy_geojson_in_ormiban_panel(): void
    {
        $ministerUser = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $map = app(MunicipalityMap::class);
        $tempPath = storage_path('framework/testing/test_malha.geojson');
        File::ensureDirectoryExists(dirname($tempPath));
        File::put($tempPath, json_encode(['type' => 'FeatureCollection', 'features' => []]));

        // Bind mock map or fake path
        $mapMock = $this->createMock(MunicipalityMap::class);
        $mapMock->method('exists')->willReturn(true);
        $mapMock->method('path')->willReturn($tempPath);
        $this->app->instance(MunicipalityMap::class, $mapMock);

        $response = $this->actingAs($ministerUser)->get('/ormiban/estrategia/malha.geojson');
        $response->assertSuccessful();
        $this->assertStringContainsString('application/geo+json', (string) $response->headers->get('Content-Type'));

        @unlink($tempPath);
    }

    public function test_inactive_minister_cannot_access_strategy_screens_in_ormiban_panel(): void
    {
        $municipality = Municipality::factory()->create();
        $inactiveMinister = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => false,
            'must_set_password' => false,
        ]);

        foreach ($this->ormibanStrategyUrls($municipality) as $url) {
            $response = $this->actingAs($inactiveMinister)->get($url);
            // Inactive user should either be 403 or redirected by auth middleware
            $this->assertTrue(in_array($response->getStatusCode(), [302, 403], true));
        }

        $geojsonResponse = $this->actingAs($inactiveMinister)->get('/ormiban/estrategia/malha.geojson');
        $this->assertTrue(in_array($geojsonResponse->getStatusCode(), [302, 403], true));
    }

    public function test_minister_cannot_access_strategy_in_admin_panel(): void
    {
        $municipality = Municipality::factory()->create();
        $ministerUser = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $adminUrls = [
            '/admin/estrategia',
            '/admin/estrategia/municipios',
            "/admin/estrategia/municipios/{$municipality->id}",
            '/admin/estrategia/metodologia',
            '/admin/estrategia/malha.geojson',
        ];

        foreach ($adminUrls as $url) {
            $response = $this->actingAs($ministerUser)->get($url);
            $this->assertTrue(in_array($response->getStatusCode(), [302, 403], true));
        }
    }

    public function test_municipalities_remain_read_only_for_ministers(): void
    {
        $ministerUser = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
        ]);
        $municipality = Municipality::factory()->create();

        $this->assertTrue($ministerUser->can('viewAny', Municipality::class));
        $this->assertTrue($ministerUser->can('view', $municipality));
        $this->assertFalse($ministerUser->can('create', Municipality::class));
        $this->assertFalse($ministerUser->can('update', $municipality));
        $this->assertFalse($ministerUser->can('delete', $municipality));
    }

    public function test_view_municipality_links_to_own_church_and_renders_safe_text_for_other_churches_and_projects(): void
    {
        $municipality = $this->municipality(['name' => 'Anápolis']);

        $ownChurch = Church::factory()->create([
            'municipality_id' => $municipality->id,
            'name' => 'Primeira Igreja Batista de Anápolis',
            'is_active' => true,
        ]);

        $otherChurch = Church::factory()->create([
            'municipality_id' => $municipality->id,
            'name' => 'Segunda Igreja Batista de Anápolis',
            'is_active' => true,
        ]);

        $socialProject = SocialProject::factory()->published()->create([
            'church_id' => $otherChurch->id,
            'title' => 'Projeto Esperança',
        ]);

        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'church_id' => $ownChurch->id,
            'must_set_password' => false,
        ]);

        Minister::factory()->create([
            'user_id' => $user->id,
            'church_id' => $ownChurch->id,
        ]);

        Filament::setCurrentPanel(Filament::getPanel('ormiban'));
        $this->actingAs($user);

        Livewire::test(ViewMunicipality::class, ['record' => $municipality->getRouteKey()])
            ->assertSee('Primeira Igreja Batista de Anápolis')
            ->assertSee(EditMyChurch::getUrl())
            ->assertSee('Segunda Igreja Batista de Anápolis')
            ->assertDontSee("/admin/churches/{$otherChurch->id}/edit")
            ->assertSee('Projeto Esperança')
            ->assertDontSee("/admin/social-projects/{$socialProject->id}/edit");
    }

    public function test_strategy_navigation_group_is_visible_in_ormiban_panel(): void
    {
        $user = User::factory()->create([
            'role' => UserRole::MINISTER,
            'is_active' => true,
            'must_set_password' => false,
        ]);

        $response = $this->actingAs($user)->get('/ormiban');
        $response->assertSuccessful();
        $response->assertSee('Estratégia');
        $response->assertSee('Visão de Goiás');
        $response->assertSee('Municípios');
        $response->assertSee('Metodologia e fontes');
    }
}
