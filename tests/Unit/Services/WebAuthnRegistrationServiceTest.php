<?php

namespace Tests\Unit\Services;

use App\Models\Passkey;
use App\Models\User;
use App\Services\WebAuthn\Exceptions\WebAuthnCeremonyException;
use App\Services\WebAuthn\WebAuthnRegistrationService;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorData;

class WebAuthnRegistrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private WebAuthnRegistrationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cbngo.org.br',
            'app.name' => 'CBN-GO',
        ]);
        $this->service = app(WebAuthnRegistrationService::class);
    }

    public function test_generates_creation_options_with_correct_rp_and_user_verification(): void
    {
        $user = User::factory()->create([
            'email' => 'pastor@cbngo.org.br',
            'name' => 'Pastor Silva',
        ]);

        $existingPasskey = Passkey::factory()->create([
            'user_id' => $user->id,
            'credential_id' => 'existing-cred-id-123',
        ]);

        $options = $this->service->generateCreationOptions($user);

        $this->assertSame('cbngo.org.br', $options['rp']['id']);
        $this->assertSame('CBN-GO', $options['rp']['name']);
        $this->assertSame($user->email, $options['user']['name']);
        $this->assertSame($user->name, $options['user']['displayName']);
        $this->assertSame((string) $user->id, Base64UrlSafe::decodeNoPadding($options['user']['id']));
        $this->assertNotEmpty($options['challenge']);
        $this->assertSame('required', $options['authenticatorSelection']['userVerification']);
        $this->assertSame('none', $options['attestation']);

        // Algoritmos suportados: ES256 (-7) e RS256 (-257)
        $algorithms = array_column($options['pubKeyCredParams'], 'alg');
        $this->assertContains(-7, $algorithms);
        $this->assertContains(-257, $algorithms);

        // Deve excluir credenciais pré-existentes do mesmo usuário
        $excludedIds = array_column($options['excludeCredentials'], 'id');
        $this->assertContains($existingPasskey->credential_id, $excludedIds);

        // Challenge deve estar armazenado no cache com chave do usuário
        $cachedChallenge = Cache::get("passkey_challenge_{$user->id}");
        $this->assertNotNull($cachedChallenge);
        $this->assertSame($options['challenge'], $cachedChallenge);
    }

    public function test_rejects_missing_or_expired_challenge(): void
    {
        $user = User::factory()->create();

        $payload = [
            'id' => 'dummy-id',
            'rawId' => 'dummy-id',
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded(json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => 'fake-challenge',
                    'origin' => 'https://cbngo.org.br',
                ], JSON_THROW_ON_ERROR)),
                'attestationObject' => 'dummy',
            ],
        ];

        $this->expectException(WebAuthnCeremonyException::class);
        $this->expectExceptionMessage('Sessão de registro expirada ou inválida.');

        $this->service->verifyAndSave($user, 'Meu Dispositivo', $payload);
    }

    public function test_rejects_replayed_challenge(): void
    {
        $user = User::factory()->create();

        $challenge = Base64UrlSafe::encodeUnpadded(random_bytes(32));
        Cache::put("passkey_challenge_{$user->id}", $challenge, 300);

        $payload = [
            'id' => 'dummy-id',
            'rawId' => 'dummy-id',
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded(json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => $challenge,
                    'origin' => 'https://cbngo.org.br',
                ], JSON_THROW_ON_ERROR)),
                'attestationObject' => 'dummy-corrupted-attestation',
            ],
        ];

        try {
            $this->service->verifyAndSave($user, 'Meu Dispositivo', $payload);
        } catch (\Throwable) {
            // Espera falhar, mas o challenge deve ter sido apagado na primeira tentativa
        }

        $this->assertNull(Cache::get("passkey_challenge_{$user->id}"), 'Challenge deve ser destruído na primeira tentativa');
    }

    public function test_rejects_invalid_origin(): void
    {
        $user = User::factory()->create();

        $rawChallenge = random_bytes(32);
        $challenge = Base64UrlSafe::encodeUnpadded($rawChallenge);
        Cache::put("passkey_challenge_{$user->id}", $challenge, 300);

        $clientDataJSON = Base64UrlSafe::encodeUnpadded(json_encode([
            'type' => 'webauthn.create',
            'challenge' => $challenge,
            'origin' => 'https://site-malicioso.com',
        ], JSON_THROW_ON_ERROR));

        $payload = [
            'id' => 'dummy-id',
            'rawId' => 'dummy-id',
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => $clientDataJSON,
                'attestationObject' => 'dummy',
            ],
        ];

        $this->expectException(WebAuthnCeremonyException::class);
        $this->expectExceptionMessage('Origem não autorizada');

        $this->service->verifyAndSave($user, 'Meu Dispositivo', $payload);
    }

    public function test_rejects_response_without_user_verification(): void
    {
        $user = User::factory()->create();

        $rawChallenge = random_bytes(32);
        $challenge = Base64UrlSafe::encodeUnpadded($rawChallenge);
        Cache::put("passkey_challenge_{$user->id}", $challenge, 300);

        $credentialId = random_bytes(32);
        // userVerification = false (flag UV ausente)
        $attestationObject = $this->createSyntheticAttestationObject('cbngo.org.br', $credentialId, isUserVerified: false);

        $payload = [
            'id' => Base64UrlSafe::encodeUnpadded($credentialId),
            'rawId' => Base64UrlSafe::encodeUnpadded($credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded(json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => $challenge,
                    'origin' => 'https://cbngo.org.br',
                ], JSON_THROW_ON_ERROR)),
                'attestationObject' => Base64UrlSafe::encodeUnpadded($attestationObject),
                'transports' => ['internal'],
            ],
        ];

        $this->expectException(WebAuthnCeremonyException::class);
        $this->expectExceptionMessage('A validação de usuário (biometria ou PIN) é estritamente obrigatória.');

        $this->service->verifyAndSave($user, 'Touch ID', $payload);
    }

    public function test_verifies_and_saves_valid_attestation_response(): void
    {
        $user = User::factory()->create();

        $rawChallenge = random_bytes(32);
        $challenge = Base64UrlSafe::encodeUnpadded($rawChallenge);
        Cache::put("passkey_challenge_{$user->id}", $challenge, 300);

        $credentialId = random_bytes(32);
        // userVerification = true (flag UV presente)
        $attestationObject = $this->createSyntheticAttestationObject('cbngo.org.br', $credentialId, isUserVerified: true);

        $payload = [
            'id' => Base64UrlSafe::encodeUnpadded($credentialId),
            'rawId' => Base64UrlSafe::encodeUnpadded($credentialId),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded(json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => $challenge,
                    'origin' => 'https://cbngo.org.br',
                ], JSON_THROW_ON_ERROR)),
                'attestationObject' => Base64UrlSafe::encodeUnpadded($attestationObject),
                'transports' => ['internal', 'hybrid'],
            ],
        ];

        $passkey = $this->service->verifyAndSave($user, 'MacBook Touch ID', $payload);

        $this->assertInstanceOf(Passkey::class, $passkey);
        $this->assertSame($user->id, $passkey->user_id);
        $this->assertSame('MacBook Touch ID', $passkey->name);
        $this->assertSame(Base64UrlSafe::encodeUnpadded($credentialId), $passkey->credential_id);
        $this->assertNotEmpty($passkey->public_key);
        $this->assertSame(['internal', 'hybrid'], $passkey->transports);
        $this->assertTrue($passkey->backup_eligible);
        $this->assertDatabaseHas('passkeys', [
            'id' => $passkey->id,
            'user_id' => $user->id,
            'name' => 'MacBook Touch ID',
            'credential_id' => Base64UrlSafe::encodeUnpadded($credentialId),
        ]);
    }

    public function test_rejects_duplicate_credential_id(): void
    {
        $user = User::factory()->create();

        $rawChallenge = random_bytes(32);
        $challenge = Base64UrlSafe::encodeUnpadded($rawChallenge);
        Cache::put("passkey_challenge_{$user->id}", $challenge, 300);

        $credentialId = random_bytes(32);
        $encodedCredId = Base64UrlSafe::encodeUnpadded($credentialId);

        // Cria antecipadamente com o mesmo ID
        Passkey::factory()->create(['credential_id' => $encodedCredId]);

        $attestationObject = $this->createSyntheticAttestationObject('cbngo.org.br', $credentialId, isUserVerified: true);

        $payload = [
            'id' => $encodedCredId,
            'rawId' => $encodedCredId,
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => Base64UrlSafe::encodeUnpadded(json_encode([
                    'type' => 'webauthn.create',
                    'challenge' => $challenge,
                    'origin' => 'https://cbngo.org.br',
                ], JSON_THROW_ON_ERROR)),
                'attestationObject' => Base64UrlSafe::encodeUnpadded($attestationObject),
            ],
        ];

        $this->expectException(WebAuthnCeremonyException::class);
        $this->expectExceptionMessage('Esta credencial já está cadastrada no sistema.');

        $this->service->verifyAndSave($user, 'Outro Note', $payload);
    }

    /**
     * Helper para construir um AttestationObject CBOR sintético válido.
     */
    private function createSyntheticAttestationObject(string $rpId, string $credentialId, bool $isUserVerified): string
    {
        $rpIdHash = hash('sha256', $rpId, true);

        $flags = AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_AT | AuthenticatorData::FLAG_BE;
        if ($isUserVerified) {
            $flags |= AuthenticatorData::FLAG_UV;
        }

        $flagsByte = chr($flags);
        $signCount = pack('N', 0);

        $aaguid = hex2bin('0102030405060708090a0b0c0d0e0f10');
        $credIdLength = pack('n', strlen($credentialId));

        // COSE Public Key para ES256 (-7) em CBOR Map
        $coseKeyMap = MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2)) // kty: EC2 (2)
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7)) // alg: -7 (ES256)
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1)) // crv: P-256 (1)
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create(random_bytes(32))) // x
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create(random_bytes(32))); // y

        $coseKeyBinary = (string) $coseKeyMap;

        $attestedCredentialData = $aaguid.$credIdLength.$credentialId.$coseKeyBinary;
        $authData = $rpIdHash.$flagsByte.$signCount.$attestedCredentialData;

        $attestationMap = MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return (string) $attestationMap;
    }
}
