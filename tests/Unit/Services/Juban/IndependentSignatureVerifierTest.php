<?php

namespace Tests\Unit\Services\Juban;

use App\Enums\JubanSigningRole;
use App\Models\JubanReport;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Juban\IndependentSignatureVerifier;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorData;

class IndependentSignatureVerifierTest extends TestCase
{
    use RefreshDatabase;

    private IndependentSignatureVerifier $verifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->verifier = new IndependentSignatureVerifier;
    }

    /**
     * @return array{signatureModel: JubanReportSignature, key: mixed, x: string, y: string}
     */
    private function createValidSignedScenario(): array
    {
        $user = User::factory()->create();
        $report = JubanReport::factory()->create();
        $pdfSha256 = hash('sha256', 'Valid Report Content');
        $report->update(['pdf_sha256' => $pdfSha256]);

        $conf = file_exists('C:/Program Files/Git/usr/ssl/openssl.cnf') ? 'C:/Program Files/Git/usr/ssl/openssl.cnf' : null;
        $configArgs = ['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC];
        if ($conf) {
            $configArgs['config'] = $conf;
        }

        $key = openssl_pkey_new($configArgs);
        $details = openssl_pkey_get_details($key);
        $x = $details['ec']['x'];
        $y = $details['ec']['y'];

        $coseKeyMap = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($x))
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($y));

        $passkey = Passkey::factory()->create([
            'user_id' => $user->id,
            'public_key' => Base64UrlSafe::encodeUnpadded((string) $coseKeyMap),
        ]);

        $nonce = bin2hex(random_bytes(32));
        $canonicalInput = "cbngo:juban-report:v1|{$report->id}|{$pdfSha256}|{$user->id}|tesouraria|{$nonce}";
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
        openssl_sign($dataToSign, $rawSignature, $key, OPENSSL_ALGO_SHA256);

        $signatureModel = JubanReportSignature::create([
            'report_id' => $report->id,
            'user_id' => $user->id,
            'role' => JubanSigningRole::TESOURARIA,
            'passkey_id' => $passkey->id,
            'nonce' => $nonce,
            'challenge' => $challengeHex,
            'client_data_json' => $clientDataJSON,
            'authenticator_data' => Base64UrlSafe::encodeUnpadded($authData),
            'signature' => Base64UrlSafe::encodeUnpadded($rawSignature),
            'pdf_sha256' => $pdfSha256,
            'signed_at' => now(),
            'ip' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        return [
            'signatureModel' => $signatureModel,
            'key' => $key,
            'x' => $x,
            'y' => $y,
        ];
    }

    public function test_independent_verification_succeeds_without_webauthn_library(): void
    {
        $scenario = $this->createValidSignedScenario();
        $signature = $scenario['signatureModel'];

        $result = $this->verifier->verify($signature);

        $this->assertTrue($result['valid']);
        $this->assertNull($result['reason']);
        $this->assertEquals($signature->challenge, $result['details']['reconstructed_challenge']);
        $this->assertTrue($result['details']['user_verified']);
    }

    public function test_independent_verification_fails_if_signature_is_tampered(): void
    {
        $scenario = $this->createValidSignedScenario();
        $signature = $scenario['signatureModel'];

        // Altera 1 byte da assinatura
        $raw = Base64UrlSafe::decodeNoPadding($signature->signature);
        $tampered = $raw;
        $tampered[10] = chr(ord($tampered[10]) ^ 0xFF);
        $signature->signature = Base64UrlSafe::encodeUnpadded($tampered);

        $result = $this->verifier->verify($signature);

        $this->assertFalse($result['valid']);
        $this->assertEquals('Assinatura criptográfica inválida para a chave pública fornecida.', $result['reason']);
    }

    public function test_independent_verification_fails_if_challenge_parameters_differ(): void
    {
        $scenario = $this->createValidSignedScenario();
        $signature = $scenario['signatureModel'];

        // Modifica o nonce gravado
        $signature->nonce = bin2hex(random_bytes(32));

        $result = $this->verifier->verify($signature);

        $this->assertFalse($result['valid']);
        $this->assertEquals('O challenge gravado não corresponde à fórmula canônica cbngo:juban-report:v1.', $result['reason']);
    }

    public function test_independent_verification_fails_if_user_verification_bit_is_missing(): void
    {
        $scenario = $this->createValidSignedScenario();
        $signature = $scenario['signatureModel'];

        $authDataBytes = Base64UrlSafe::decodeNoPadding($signature->authenticator_data);
        // Remove flag UV (bit 2 = 0x04)
        $authDataBytes[32] = chr(ord($authDataBytes[32]) & ~0x04);
        $signature->authenticator_data = Base64UrlSafe::encodeUnpadded($authDataBytes);

        $result = $this->verifier->verify($signature);

        $this->assertFalse($result['valid']);
        $this->assertEquals('A flag de verificação de usuário (UV) não está ativa no authenticatorData.', $result['reason']);
    }
}
