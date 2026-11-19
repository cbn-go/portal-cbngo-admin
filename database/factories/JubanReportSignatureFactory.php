<?php

namespace Database\Factories;

use App\Enums\JubanSigningRole;
use App\Models\JubanReport;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use ParagonIE\ConstantTime\Base64UrlSafe;

/**
 * @extends Factory<JubanReportSignature>
 */
class JubanReportSignatureFactory extends Factory
{
    protected $model = JubanReportSignature::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $nonce = bin2hex(random_bytes(32));
        $pdfSha256 = hash('sha256', fake()->text(200));
        $userId = User::factory();
        $challenge = hash('sha256', "cbngo:juban-report:v1|1|{$pdfSha256}|1|tesouraria|{$nonce}");

        return [
            'report_id' => JubanReport::factory(),
            'user_id' => $userId,
            'role' => JubanSigningRole::TESOURARIA,
            'passkey_id' => Passkey::factory()->for($userId),
            'nonce' => $nonce,
            'challenge' => $challenge,
            'client_data_json' => json_encode([
                'type' => 'webauthn.get',
                'challenge' => Base64UrlSafe::encodeUnpadded(hex2bin($challenge) ?: ''),
                'origin' => 'https://cbngo.org.br',
            ], JSON_THROW_ON_ERROR),
            'authenticator_data' => Base64UrlSafe::encodeUnpadded(random_bytes(37)),
            'signature' => Base64UrlSafe::encodeUnpadded(random_bytes(64)),
            'pdf_sha256' => $pdfSha256,
            'signed_at' => now(),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
