<?php

namespace Tests\Unit\Services\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Models\JubanReport;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Juban\Exceptions\JubanSigningException;
use App\Services\Juban\JubanReportSigningService;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorData;

class JubanReportSigningServiceTest extends TestCase
{
    use RefreshDatabase;

    private JubanReportSigningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cbngo.org.br',
            'app.name' => 'CBN-GO',
        ]);

        Storage::fake('local');
        $this->service = app(JubanReportSigningService::class);
    }

    /**
     * Helper to create a fake PDF in storage and return its hash.
     */
    private function createReportPdf(JubanReport $report, string $content = 'Dummy PDF Content'): string
    {
        $path = "reports/juban/report-{$report->year}-q{$report->quarter}.pdf";
        Storage::disk('local')->put($path, $content);
        $hash = hash('sha256', $content);

        $report->update([
            'pdf_path' => $path,
            'pdf_sha256' => $hash,
        ]);

        return $hash;
    }

    /**
     * Helper to generate a test EC key and a registered Passkey model for a user.
     *
     * @return array{passkey: Passkey, key: mixed, x: string, y: string}
     */
    private function createTestPasskey(User $user): array
    {
        $conf = file_exists('C:/Program Files/Git/usr/ssl/openssl.cnf') ? 'C:/Program Files/Git/usr/ssl/openssl.cnf' : null;
        $configArgs = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        if ($conf) {
            $configArgs['config'] = $conf;
        }

        $key = openssl_pkey_new($configArgs);
        $details = openssl_pkey_get_details($key);
        $x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $coseKeyMap = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y));

        $credentialId = Base64UrlSafe::encodeUnpadded(random_bytes(32));

        $passkey = Passkey::factory()->create([
            'user_id' => $user->id,
            'credential_id' => $credentialId,
            'public_key' => Base64UrlSafe::encodeUnpadded((string) $coseKeyMap),
            'sign_count' => 0,
        ]);

        return ['passkey' => $passkey, 'key' => $key, 'x' => $x, 'y' => $y];
    }

    /**
     * Helper to build a signed WebAuthn assertion payload.
     *
     * @param  array{passkey: Passkey, key: mixed, x: string, y: string}  $keyData
     * @return array<string, mixed>
     */
    private function buildAssertionPayload(
        array $keyData,
        string $rawChallengeBinary,
        int $signCount = 1,
        bool $userVerified = true,
        string $origin = 'https://cbngo.org.br'
    ): array {
        $clientDataJSON = json_encode([
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($rawChallengeBinary),
            'origin' => $origin,
        ], JSON_THROW_ON_ERROR);

        $rpIdHash = hash('sha256', 'cbngo.org.br', true);
        $flagsByte = chr(AuthenticatorData::FLAG_UP | ($userVerified ? AuthenticatorData::FLAG_UV : 0));
        $signCountBytes = pack('N', $signCount);
        $authData = $rpIdHash.$flagsByte.$signCountBytes;

        $dataToSign = $authData.hash('sha256', $clientDataJSON, true);
        openssl_sign($dataToSign, $rawSignature, $keyData['key'], OPENSSL_ALGO_SHA256);

        return [
            'id' => $keyData['passkey']->credential_id,
            'rawId' => $keyData['passkey']->credential_id,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded($clientDataJSON),
                'authenticatorData' => Base64UrlSafe::encodeUnpadded($authData),
                'signature' => Base64UrlSafe::encodeUnpadded($rawSignature),
            ],
        ];
    }

    public function test_challenge_follows_strict_format_and_expires_in_5_minutes(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);
        $this->createTestPasskey($treasurer);

        $options = $this->service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);

        $this->assertNotEmpty($options['challenge']);
        $this->assertEquals('cbngo.org.br', $options['rpId']);
        $this->assertEquals('required', $options['userVerification']);

        $cached = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");
        $this->assertIsArray($cached);
        $this->assertEquals(JubanSigningRole::TESOURARIA->value, $cached['role']);
        $this->assertEquals(64, strlen($cached['nonce'])); // 32 bytes hex
        $this->assertEquals(64, strlen($cached['challenge_hex']));

        $expectedInput = "cbngo:juban-report:v1|{$report->id}|{$report->pdf_sha256}|{$treasurer->id}|tesouraria|{$cached['nonce']}";
        $this->assertEquals(hash('sha256', $expectedInput), $cached['challenge_hex']);
        $this->assertEquals(Base64UrlSafe::encodeUnpadded(hash('sha256', $expectedInput, true)), $options['challenge']);
    }

    public function test_cannot_generate_options_for_wrong_role_or_out_of_order_status(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        // 1. Relatório em aguardando_tesouraria: presidente tenta assinar
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('O relatório não está aguardando a assinatura da Presidência.');
        $this->service->generateSigningOptions($report, $president, JubanSigningRole::PRESIDENCIA);
    }

    public function test_treasurer_cannot_sign_when_waiting_presidency(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_PRESIDENCIA]);
        $this->createReportPdf($report);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('O relatório não está aguardando a assinatura da Tesouraria.');
        $this->service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
    }

    public function test_same_user_cannot_sign_both_treasury_and_presidency(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);
        $keyData = $this->createTestPasskey($superAdmin);

        // Assina como tesouraria
        $options = $this->service->generateSigningOptions($report, $superAdmin, JubanSigningRole::TESOURARIA);
        $cached = Cache::get("juban_signing_{$report->id}_{$superAdmin->id}");
        $payload = $this->buildAssertionPayload($keyData, $cached['challenge_binary']);
        $this->service->verifyAndSign($report, $superAdmin, JubanSigningRole::TESOURARIA, $payload);

        $this->assertEquals(JubanReportStatus::AGUARDANDO_PRESIDENCIA, $report->fresh()->status);

        // Tenta assinar como presidência com o mesmo usuário
        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('A mesma pessoa não pode assinar nos dois papéis.');
        $this->service->generateSigningOptions($report->fresh(), $superAdmin, JubanSigningRole::PRESIDENCIA);
    }

    public function test_signing_is_rejected_if_stored_pdf_file_is_tampered_or_missing(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report, 'Original PDF');
        $this->createTestPasskey($treasurer);

        // Altera o arquivo físico no storage
        Storage::disk('local')->put($report->pdf_path, 'Tampered Content');

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('O arquivo PDF do relatório foi alterado ou corrompido.');
        $this->service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
    }

    public function test_signing_is_rejected_if_passkey_does_not_belong_to_user(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $otherUser = User::factory()->create(['role' => UserRole::AUTHOR, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);

        $this->createTestPasskey($treasurer);
        $otherKeyData = $this->createTestPasskey($otherUser);

        $this->service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
        $cached = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");

        // Assina com a chave do outro usuário
        $payload = $this->buildAssertionPayload($otherKeyData, $cached['challenge_binary']);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('A passkey utilizada não pertence ao usuário autenticado.');
        $this->service->verifyAndSign($report, $treasurer, JubanSigningRole::TESOURARIA, $payload);
    }

    public function test_signing_is_rejected_without_user_verification_flag(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);
        $keyData = $this->createTestPasskey($treasurer);

        $this->service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
        $cached = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");

        // userVerified = false
        $payload = $this->buildAssertionPayload($keyData, $cached['challenge_binary'], signCount: 1, userVerified: false);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('A validação de usuário (biometria ou PIN) é estritamente obrigatória.');
        $this->service->verifyAndSign($report, $treasurer, JubanSigningRole::TESOURARIA, $payload);
    }

    public function test_signing_is_rejected_if_challenge_is_reused_or_expired(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);
        $keyData = $this->createTestPasskey($treasurer);

        $this->service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
        $cached = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");
        $payload = $this->buildAssertionPayload($keyData, $cached['challenge_binary']);

        // Primeiro uso OK
        $this->service->verifyAndSign($report, $treasurer, JubanSigningRole::TESOURARIA, $payload);

        // Segundo uso com mesmo payload (challenge já consumido/invalidado)
        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('Sessão de assinatura expirada ou inválida.');
        $this->service->verifyAndSign($report, $treasurer, JubanSigningRole::TESOURARIA, $payload);
    }

    public function test_treasurer_signs_report_advancing_status_to_waiting_presidency(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);
        $keyData = $this->createTestPasskey($treasurer);

        $this->service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
        $cached = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");
        $payload = $this->buildAssertionPayload($keyData, $cached['challenge_binary'], signCount: 10);

        $signature = $this->service->verifyAndSign($report, $treasurer, JubanSigningRole::TESOURARIA, $payload, '127.0.0.1', 'Mozilla/5.0');

        $this->assertEquals(JubanSigningRole::TESOURARIA, $signature->role);
        $this->assertEquals($treasurer->id, $signature->user_id);
        $this->assertEquals($report->id, $signature->report_id);
        $this->assertEquals(JubanReportStatus::AGUARDANDO_PRESIDENCIA, $report->fresh()->status);
        $this->assertEquals(10, $keyData['passkey']->fresh()->sign_count);
        $this->assertNotNull($keyData['passkey']->fresh()->last_used_at);
    }

    public function test_president_signs_report_advancing_status_to_assinado(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);
        $this->createReportPdf($report);

        $treasurerKey = $this->createTestPasskey($treasurer);
        $presidentKey = $this->createTestPasskey($president);

        // 1. Tesouraria assina
        $this->service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
        $cachedT = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");
        $payloadT = $this->buildAssertionPayload($treasurerKey, $cachedT['challenge_binary']);
        $this->service->verifyAndSign($report, $treasurer, JubanSigningRole::TESOURARIA, $payloadT);

        // 2. Presidência assina
        $this->service->generateSigningOptions($report->fresh(), $president, JubanSigningRole::PRESIDENCIA);
        $cachedP = Cache::get("juban_signing_{$report->id}_{$president->id}");
        $payloadP = $this->buildAssertionPayload($presidentKey, $cachedP['challenge_binary']);
        $sigPresident = $this->service->verifyAndSign($report->fresh(), $president, JubanSigningRole::PRESIDENCIA, $payloadP);

        $this->assertEquals(JubanSigningRole::PRESIDENCIA, $sigPresident->role);
        $this->assertEquals(JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL, $report->fresh()->status);
        $this->assertEquals(2, $report->fresh()->signatures()->count());
    }
}
