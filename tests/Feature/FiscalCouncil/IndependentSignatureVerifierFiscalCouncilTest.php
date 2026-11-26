<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Juban\IndependentSignatureVerifier;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorData;

class IndependentSignatureVerifierFiscalCouncilTest extends TestCase
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

    public function test_independent_verifier_validates_council_signature_successfully(): void
    {
        $user = User::factory()->create(['name' => 'Abílio Rocha', 'role' => UserRole::FISCAL_COUNCIL]);
        $member = FiscalCouncilMember::factory()->create([
            'user_id' => $user->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create([
            'pdf_sha256' => hash('sha256', 'report-content'),
        ]);

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::ISSUED,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da CBN-GO',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $user->id,
            'pdf_path' => 'dummy.pdf',
            'pdf_sha256' => hash('sha256', 'opinion-content'),
        ]);

        $keyData = $this->createPasskeyWithKey($user);

        $nonce = bin2hex(random_bytes(32));
        $canonicalInput = "cbngo:juban-opinion:v1|{$report->id}|{$report->pdf_sha256}|{$opinion->id}|{$opinion->pdf_sha256}|{$user->id}|conselho_relator|{$nonce}";
        $challengeHex = hash('sha256', $canonicalInput);
        $challengeBinary = hash('sha256', $canonicalInput, true);

        $clientDataJSON = json_encode([
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($challengeBinary),
            'origin' => 'https://cbngo.org.br',
        ], JSON_THROW_ON_ERROR);

        $rpIdHash = hash('sha256', 'cbngo.org.br', true);
        $flagsByte = chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_UV);
        $signCountBytes = pack('N', 1);
        $authData = $rpIdHash.$flagsByte.$signCountBytes;

        $dataToSign = $authData.hash('sha256', $clientDataJSON, true);
        openssl_sign($dataToSign, $rawSignature, $keyData['key'], OPENSSL_ALGO_SHA256);

        $signature = JubanReportSignature::query()->create([
            'report_id' => $report->id,
            'opinion_id' => $opinion->id,
            'user_id' => $user->id,
            'role' => JubanSigningRole::CONSELHO_RELATOR,
            'passkey_id' => $keyData['passkey']->id,
            'nonce' => $nonce,
            'challenge' => $challengeHex,
            'client_data_json' => $clientDataJSON,
            'authenticator_data' => Base64UrlSafe::encodeUnpadded($authData),
            'signature' => Base64UrlSafe::encodeUnpadded($rawSignature),
            'pdf_sha256' => $opinion->pdf_sha256,
            'signed_at' => now(),
        ]);

        $verifier = app(IndependentSignatureVerifier::class);
        $verification = $verifier->verify($signature);

        $this->assertTrue($verification['valid']);
        $this->assertNull($verification['reason']);
        $this->assertSame($challengeHex, $verification['details']['reconstructed_challenge']);
    }

    public function test_independent_verifier_detects_tampered_opinion_sha256(): void
    {
        $user = User::factory()->create(['name' => 'Abílio Rocha', 'role' => UserRole::FISCAL_COUNCIL]);
        $member = FiscalCouncilMember::factory()->create([
            'user_id' => $user->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create([
            'pdf_sha256' => hash('sha256', 'report-content'),
        ]);

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::ISSUED,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da CBN-GO',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $user->id,
            'pdf_path' => 'dummy.pdf',
            'pdf_sha256' => hash('sha256', 'opinion-content'),
        ]);

        $keyData = $this->createPasskeyWithKey($user);

        $signature = JubanReportSignature::query()->create([
            'report_id' => $report->id,
            'opinion_id' => $opinion->id,
            'user_id' => $user->id,
            'role' => JubanSigningRole::CONSELHO_RELATOR,
            'passkey_id' => $keyData['passkey']->id,
            'nonce' => bin2hex(random_bytes(32)),
            'challenge' => 'tampered_challenge_value',
            'client_data_json' => '{}',
            'authenticator_data' => 'dummy',
            'signature' => 'dummy',
            'pdf_sha256' => 'tampered_sha256',
            'signed_at' => now(),
        ]);

        $verifier = app(IndependentSignatureVerifier::class);
        $verification = $verifier->verify($signature);

        $this->assertFalse($verification['valid']);
        $this->assertStringContainsString('cbngo:juban-opinion:v1', $verification['reason']);
    }
}
