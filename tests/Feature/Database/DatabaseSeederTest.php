<?php

namespace Tests\Feature\Database;

use App\Enums\UserRole;
use App\Models\Church;
use App\Models\User;
use Database\Seeders\SuperAdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_super_admin_and_polo_churches(): void
    {
        $this->seed();

        $admin = User::query()->where('email', SuperAdminSeeder::EMAIL)->first();

        $this->assertInstanceOf(User::class, $admin);
        $this->assertSame(SuperAdminSeeder::NAME, $admin->name);
        $this->assertSame(UserRole::SUPER_ADMIN, $admin->role);
        $this->assertTrue($admin->is_active);
        $this->assertNull($admin->church_id);

        $cities = [
            'Goiânia',
            'Aparecida de Goiânia',
            'Anápolis',
            'Rio Verde',
            'Jataí',
            'Catalão',
            'Itumbiara',
            'Luziânia',
        ];

        foreach ($cities as $city) {
            $this->assertDatabaseHas('churches', [
                'city' => $city,
                'state' => 'GO',
                'is_active' => true,
            ]);
        }

        $this->assertSame(count($cities), Church::query()->whereIn('city', $cities)->count());
    }
}
