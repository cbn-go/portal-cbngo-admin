<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            MunicipalitySeeder::class,
            ChurchSeeder::class,
            SuperAdminSeeder::class,
            StatuteSeeder::class,
            InstitutionalSettingSeeder::class,
            BoardTerm20262029Seeder::class,
        ]);

        if (app()->environment('local', 'testing')) {
            $this->call(DevelopmentContentSeeder::class);
        }
    }
}
