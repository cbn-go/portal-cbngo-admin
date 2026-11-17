<?php

namespace Database\Factories;

use App\Models\Passkey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Passkey>
 */
class PasskeyFactory extends Factory
{
    protected $model = Passkey::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['MacBook Pro Touch ID', 'iPhone 15', 'Windows Hello', 'YubiKey 5C']),
            'credential_id' => rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='),
            'public_key' => rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '='),
            'sign_count' => 0,
            'transports' => ['internal', 'hybrid'],
            'aaguid' => Str::uuid()->toString(),
            'backup_eligible' => true,
            'last_used_at' => null,
        ];
    }
}
