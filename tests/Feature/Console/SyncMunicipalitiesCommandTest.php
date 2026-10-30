<?php

namespace Tests\Feature\Console;

use App\Models\Church;
use App\Models\Municipality;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SyncMunicipalitiesCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_command_upserts_municipalities_from_ibge_api_and_is_idempotent(): void
    {
        $fixture = json_decode((string) file_get_contents(database_path('data/ibge_go_municipios.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/*' => Http::response($fixture, 200),
        ]);

        $this->artisan('strategy:sync-municipalities')
            ->expectsOutputToContain('246')
            ->assertSuccessful();

        $this->assertSame(246, Municipality::query()->count());

        $goiania = Municipality::query()->where('ibge_code', '5208707')->first();
        $this->assertNotNull($goiania);
        $this->assertSame('Goiânia', $goiania->name);
        $this->assertSame('goiania', $goiania->slug);
        $this->assertSame('goiania', $goiania->normalized_name);
        $this->assertSame('Goiânia', $goiania->immediate_region_name);
        $this->assertSame('Goiânia', $goiania->intermediate_region_name);

        // Run second time to verify idempotency
        $this->artisan('strategy:sync-municipalities')
            ->assertSuccessful();

        $this->assertSame(246, Municipality::query()->count());
    }

    public function test_sync_command_links_churches_by_normalized_city(): void
    {
        $fixture = json_decode((string) file_get_contents(database_path('data/ibge_go_municipios.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/*' => Http::response($fixture, 200),
        ]);

        $church1 = Church::factory()->create(['name' => 'IBN Goianésia', 'city' => 'goianésia']);
        $church2 = Church::factory()->create(['name' => 'IBN Montes Belos', 'city' => 'São Luís de Montes Belos']);
        $church3 = Church::factory()->create(['name' => 'IBN Aliança', 'city' => "são joão d'aliança"]);

        $this->artisan('strategy:sync-municipalities')
            ->assertSuccessful();

        $church1->refresh();
        $church2->refresh();
        $church3->refresh();

        $this->assertNotNull($church1->municipality_id);
        $this->assertSame('Goianésia', $church1->municipality->name);
        $this->assertSame('Goianésia', $church1->city);

        $this->assertNotNull($church2->municipality_id);
        $this->assertSame('São Luís de Montes Belos', $church2->municipality->name);
        $this->assertSame('São Luís de Montes Belos', $church2->city);

        $this->assertNotNull($church3->municipality_id);
        $this->assertSame("São João d'Aliança", $church3->municipality->name);
        $this->assertSame("São João d'Aliança", $church3->city);
    }

    public function test_sync_command_reports_unmatched_churches_and_leaves_null_foreign_key(): void
    {
        $fixture = json_decode((string) file_get_contents(database_path('data/ibge_go_municipios.json')), true);

        Http::fake([
            'https://servicodados.ibge.gov.br/*' => Http::response($fixture, 200),
        ]);

        $churchValid = Church::factory()->create(['name' => 'IBN Goiânia', 'city' => 'Goiânia']);
        $churchInvalid1 = Church::factory()->create(['name' => 'IBN Brasília', 'city' => 'Brasília']);
        $churchInvalid2 = Church::factory()->create(['name' => 'IBN Outra', 'city' => 'Cidade Inexistente']);

        $this->artisan('strategy:sync-municipalities')
            ->expectsOutputToContain('Brasília')
            ->expectsOutputToContain('Cidade Inexistente')
            ->assertSuccessful();

        $churchValid->refresh();
        $churchInvalid1->refresh();
        $churchInvalid2->refresh();

        $this->assertNotNull($churchValid->municipality_id);
        $this->assertNull($churchInvalid1->municipality_id);
        $this->assertNull($churchInvalid2->municipality_id);
    }

    public function test_sync_command_supports_local_file_option(): void
    {
        $this->artisan('strategy:sync-municipalities', [
            '--file' => database_path('data/ibge_go_municipios.json'),
        ])->assertSuccessful();

        $this->assertSame(246, Municipality::query()->count());
    }
}
