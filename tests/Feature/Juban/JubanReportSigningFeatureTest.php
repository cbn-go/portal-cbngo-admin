<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Models\JubanReport;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Juban\IndependentSignatureVerifier;
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

class JubanReportSigningFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cbngo.org.br',
            'app.name' => 'CBN-GO',
        ]);

        Storage::fake('local');
    }

    private function createPasskeyWithKey(User $user): array
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

        $passkey = Passkey::factory()->create([
            'user_id' => $user->id,
            'credential_id' => Base64UrlSafe::encodeUnpadded(random_bytes(32)),
            'public_key' => Base64UrlSafe::encodeUnpadded((string) $coseKeyMap),
            'sign_count' => 0,
        ]);

        return ['passkey' => $passkey, 'key' => $key];
    }

    private function buildPayload(array $keyData, string $challengeBinary, int $signCount = 1): array
    {
        $clientDataJSON = json_encode([
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($challengeBinary),
            'origin' => 'https://cbngo.org.br',
        ], JSON_THROW_ON_ERROR);

        $rpIdHash = hash('sha256', 'cbngo.org.br', true);
        $flagsByte = chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_UV);
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

    public function test_full_signing_ceremony_e2e_from_treasurer_to_president_flow(): void
    {
        $signingService = app(JubanReportSigningService::class);
        $verifier = app(IndependentSignatureVerifier::class);

        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $pdfPath = "reports/juban/report-{$report->year}-q{$report->quarter}.pdf";
        Storage::disk('local')->put($pdfPath, 'Oficial PDF Content Q3 2026');
        $report->update([
            'pdf_path' => $pdfPath,
            'pdf_sha256' => hash('sha256', 'Oficial PDF Content Q3 2026'),
        ]);

        $treasurerPasskey = $this->createPasskeyWithKey($treasurer);
        $presidentPasskey = $this->createPasskeyWithKey($president);

        // 1. Tesouraria obtém options e assina
        $optionsT = $signingService->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
        $cachedT = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");
        $payloadT = $this->buildPayload($treasurerPasskey, $cachedT['challenge_binary'], 1);

        $sigT = $signingService->verifyAndSign(
            $report,
            $treasurer,
            JubanSigningRole::TESOURARIA,
            $payloadT,
            '192.168.1.100',
            'Mobile Safari'
        );

        $this->assertEquals(JubanReportStatus::AGUARDANDO_PRESIDENCIA, $report->fresh()->status);
        $this->assertDatabaseHas('juban_report_signatures', [
            'id' => $sigT->id,
            'report_id' => $report->id,
            'user_id' => $treasurer->id,
            'role' => 'tesouraria',
            'ip' => '192.168.1.100',
        ]);

        // Valida que a assinatura da tesouraria passa na verificação independente
        $verifyT = $verifier->verify($sigT);
        $this->assertTrue($verifyT['valid']);

        // 2. Presidência obtém options e assina
        $optionsP = $signingService->generateSigningOptions($report->fresh(), $president, JubanSigningRole::PRESIDENCIA);
        $cachedP = Cache::get("juban_signing_{$report->id}_{$president->id}");
        $payloadP = $this->buildPayload($presidentPasskey, $cachedP['challenge_binary'], 1);

        $sigP = $signingService->verifyAndSign(
            $report->fresh(),
            $president,
            JubanSigningRole::PRESIDENCIA,
            $payloadP,
            '192.168.1.101',
            'Chrome Desktop'
        );

        $this->assertEquals(JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL, $report->fresh()->status);
        $this->assertDatabaseHas('juban_report_signatures', [
            'id' => $sigP->id,
            'report_id' => $report->id,
            'user_id' => $president->id,
            'role' => 'presidencia',
            'ip' => '192.168.1.101',
        ]);

        // Valida que a assinatura da presidência passa na verificação independente
        $verifyP = $verifier->verify($sigP);
        $this->assertTrue($verifyP['valid']);

        // Ambas as relações funcionam
        $this->assertNotNull($report->fresh()->treasurySignature);
        $this->assertNotNull($report->fresh()->presidencySignature);
        $this->assertEquals($sigT->id, $report->fresh()->treasurySignature->id);
        $this->assertEquals($sigP->id, $report->fresh()->presidencySignature->id);
    }

    public function test_database_signature_record_stores_all_audit_fields(): void
    {
        $signingService = app(JubanReportSigningService::class);
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::AGUARDANDO_TESOURARIA]);

        $pdfPath = "reports/juban/report-{$report->year}-q{$report->quarter}.pdf";
        Storage::disk('local')->put($pdfPath, 'Audited PDF Content');
        $pdfHash = hash('sha256', 'Audited PDF Content');
        $report->update(['pdf_path' => $pdfPath, 'pdf_sha256' => $pdfHash]);

        $passkeyData = $this->createPasskeyWithKey($treasurer);

        $signingService->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
        $cached = Cache::get("juban_signing_{$report->id}_{$treasurer->id}");
        $payload = $this->buildPayload($passkeyData, $cached['challenge_binary'], 5);

        $sig = $signingService->verifyAndSign(
            $report,
            $treasurer,
            JubanSigningRole::TESOURARIA,
            $payload,
            '200.100.50.25',
            'Windows Chrome 120'
        );

        $freshSig = $sig->fresh();
        $this->assertEquals($report->id, $freshSig->report_id);
        $this->assertEquals($treasurer->id, $freshSig->user_id);
        $this->assertEquals(JubanSigningRole::TESOURARIA, $freshSig->role);
        $this->assertEquals($passkeyData['passkey']->id, $freshSig->passkey_id);
        $this->assertEquals($cached['nonce'], $freshSig->nonce);
        $this->assertEquals($cached['challenge_hex'], $freshSig->challenge);
        $this->assertNotEmpty($freshSig->client_data_json);
        $this->assertNotEmpty($freshSig->authenticator_data);
        $this->assertNotEmpty($freshSig->signature);
        $this->assertEquals($pdfHash, $freshSig->pdf_sha256);
        $this->assertNotNull($freshSig->signed_at);
        $this->assertEquals('200.100.50.25', $freshSig->ip);
        $this->assertEquals('Windows Chrome 120', $freshSig->user_agent);
    }
}
