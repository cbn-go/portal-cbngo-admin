<?php

namespace Database\Seeders;

use App\Models\Church;
use Illuminate\Database\Seeder;

class ChurchSeeder extends Seeder
{
    /**
     * Municípios polo de Goiás cobertos pelo diretório modelo.
     *
     * @var list<string>
     */
    public const POLO_CITIES = [
        'Goiânia',
        'Aparecida de Goiânia',
        'Anápolis',
        'Rio Verde',
        'Jataí',
        'Catalão',
        'Itumbiara',
        'Luziânia',
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (self::POLO_CITIES as $city) {
            Church::query()->updateOrCreate(
                ['name' => "Igreja Batista Nacional de {$city}"],
                [
                    'city' => $city,
                    'state' => 'GO',
                    'is_active' => true,
                ],
            );
        }
    }
}
