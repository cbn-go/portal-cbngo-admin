<?php

namespace Database\Seeders;

use App\Models\Municipality;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MunicipalitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = database_path('data/ibge_go_municipios.json');

        if (! file_exists($filePath)) {
            return;
        }

        $data = json_decode((string) file_get_contents($filePath), true);

        if (! is_array($data)) {
            return;
        }

        foreach ($data as $item) {
            $ibgeCode = (string) ($item['id'] ?? '');
            $name = trim((string) ($item['nome'] ?? ''));

            if (blank($ibgeCode) || blank($name)) {
                continue;
            }

            $immediateRegion = $item['regiao-imediata'] ?? [];
            $intermediateRegion = $immediateRegion['regiao-intermediaria'] ?? [];
            $microRegion = $item['microrregiao'] ?? [];
            $mesoRegion = $microRegion['mesorregiao'] ?? [];

            Municipality::query()->updateOrCreate(
                ['ibge_code' => $ibgeCode],
                [
                    'name' => $name,
                    'slug' => Str::slug($name),
                    'normalized_name' => Municipality::normalizeName($name),
                    'immediate_region_code' => (string) ($immediateRegion['id'] ?? ''),
                    'immediate_region_name' => (string) ($immediateRegion['nome'] ?? ''),
                    'intermediate_region_code' => (string) ($intermediateRegion['id'] ?? ''),
                    'intermediate_region_name' => (string) ($intermediateRegion['nome'] ?? ''),
                    'micro_region_name' => ! empty($microRegion['nome']) ? (string) $microRegion['nome'] : null,
                    'meso_region_name' => ! empty($mesoRegion['nome']) ? (string) $mesoRegion['nome'] : null,
                ]
            );
        }
    }
}
