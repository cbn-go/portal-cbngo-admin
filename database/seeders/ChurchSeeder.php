<?php

namespace Database\Seeders;

use App\Models\Church;
use App\Models\Municipality;
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
     * Coordenadas aproximadas do centro de cada município polo (latitude, longitude).
     *
     * @var array<string, array{0: string, 1: string}>
     */
    public const POLO_COORDINATES = [
        'Goiânia' => ['-16.6868912', '-49.2647943'],
        'Aparecida de Goiânia' => ['-16.8198374', '-49.2469001'],
        'Anápolis' => ['-16.3281051', '-48.9530117'],
        'Rio Verde' => ['-17.7923002', '-50.9191925'],
        'Jataí' => ['-17.8783871', '-51.7204438'],
        'Catalão' => ['-18.1656165', '-47.9440269'],
        'Itumbiara' => ['-18.4093071', '-49.2158222'],
        'Luziânia' => ['-16.2525391', '-47.9501137'],
    ];

    /**
     * Programação semanal padrão das congregações do diretório modelo.
     *
     * @var list<array{day: string, time: string, label: string}>
     */
    public const DEFAULT_SERVICE_TIMES = [
        ['day' => 'Domingo', 'time' => '09h00', 'label' => 'Escola Bíblica Dominical'],
        ['day' => 'Domingo', 'time' => '19h00', 'label' => 'Culto de Celebração'],
        ['day' => 'Quarta-feira', 'time' => '20h00', 'label' => 'Reunião de Oração e Palavra'],
    ];

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (self::POLO_CITIES as $city) {
            [$latitude, $longitude] = self::POLO_COORDINATES[$city];

            $municipality = Municipality::findByNormalizedName($city);

            Church::query()->updateOrCreate(
                ['name' => "Igreja Batista Nacional de {$city}"],
                [
                    'municipality_id' => $municipality?->id,
                    'city' => $municipality?->name ?? $city,
                    'state' => 'GO',
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'service_times' => self::DEFAULT_SERVICE_TIMES,
                    'is_active' => true,
                ],
            );
        }
    }
}
