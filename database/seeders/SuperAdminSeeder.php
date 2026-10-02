<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public const NAME = 'Super Admin';

    public const EMAIL = 'superadmin@cbngo.org.br';

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => self::NAME,
                'password' => 'password',
                'role' => UserRole::SUPER_ADMIN,
                'church_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }
}
