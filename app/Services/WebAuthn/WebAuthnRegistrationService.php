<?php

namespace App\Services\WebAuthn;

use App\Models\Passkey;
use App\Models\User;
use App\Services\WebAuthn\Exceptions\WebAuthnCeremonyException;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Illuminate\Support\Facades\Cache;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;
use Webauthn\AttestationStatement\AttestationObjectLoader;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AttestationStatement\PackedAttestationStatementSupport;
use Webauthn\AuthenticatorData;
use Webauthn\CollectedClientData;

class WebAuthnRegistrationService
{
    public const CHALLENGE_TTL_SECONDS = 300; // 5 minutos

    /**
     * @return array<string, mixed>
     */
    public function generateCreationOptions(User $user): array
    {
        $appUrl = (string) config('app.url', 'http://localhost');
        $rpId = (string) (parse_url($appUrl, PHP_URL_HOST) ?? 'localhost');
        $rpName = (string) config('app.name', 'CBN-GO');

        $rawChallenge = random_bytes(32);
        $challenge = Base64UrlSafe::encodeUnpadded($rawChallenge);

        Cache::put("passkey_challenge_{$user->id}", $challenge, self::CHALLENGE_TTL_SECONDS);

        $excludeCredentials = $user->passkeys()
            ->get(['credential_id', 'transports'])
            ->map(fn (Passkey $p) => array_filter([
                'type' => 'public-key',
                'id' => $p->credential_id,
                'transports' => $p->transports,
            ]))
            ->values()
            ->all();

        return [
            'rp' => [
                'name' => $rpName,
                'id' => $rpId,
            ],
            'user' => [
                'id' => Base64UrlSafe::encodeUnpadded((string) $user->id),
                'name' => $user->email,
                'displayName' => $user->name,
            ],
            'challenge' => $challenge,
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],   // ES256
                ['type' => 'public-key', 'alg' => -257], // RS256
            ],
            'authenticatorSelection' => [
                'userVerification' => 'required',
                'residentKey' => 'preferred',
                'requireResidentKey' => false,
            ],
            'attestation' => 'none',
            'timeout' => 60000,
            'excludeCredentials' => $excludeCredentials,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function verifyAndSave(User $user, string $name, array $payload): Passkey
    {
        // 1. O challenge deve ser lido e invalidado imediatamente (uso único)
        $expectedChallenge = Cache::pull("passkey_challenge_{$user->id}");
        if (! $expectedChallenge || ! is_string($expectedChallenge)) {
            throw new WebAuthnCeremonyException('Sessão de registro expirada ou inválida.');
        }

        // Validação da estrutura mínima do payload do navegador
        $clientDataJSON = $payload['response']['clientDataJSON'] ?? null;
        $attestationObjectRaw = $payload['response']['attestationObject'] ?? null;

        if (! is_string($clientDataJSON) || ! is_string($attestationObjectRaw)) {
            throw new WebAuthnCeremonyException('Dados de resposta da credencial incompletos.');
        }

        // 2. Decodifica e valida CollectedClientData
        try {
            $clientData = CollectedClientData::createFormJson($clientDataJSON);
        } catch (Throwable $e) {
            throw new WebAuthnCeremonyException('Estrutura de clientDataJSON inválida.', previous: $e);
        }

        if ($clientData->type !== 'webauthn.create') {
            throw new WebAuthnCeremonyException('Tipo de operação inválido.');
        }

        if ($clientData->challenge !== Base64UrlSafe::decodeNoPadding($expectedChallenge)) {
            throw new WebAuthnCeremonyException('Challenge não confere ou expirou.');
        }

        // 3. Validação estrita de Origem
        $appUrl = (string) config('app.url', 'http://localhost');
        $expectedOrigin = rtrim($appUrl, '/');
        $receivedOrigin = rtrim($clientData->origin, '/');

        if ($receivedOrigin !== $expectedOrigin) {
            throw new WebAuthnCeremonyException("Origem não autorizada: {$receivedOrigin}");
        }

        // 4. Carrega AttestationObject e extrai AuthenticatorData
        $algorithmManager = Manager::create()
            ->add(ES256::create())
            ->add(RS256::create());

        $supportManager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport,
            new PackedAttestationStatementSupport($algorithmManager),
        ]);

        $loader = AttestationObjectLoader::create($supportManager);

        try {
            $attestationObject = $loader->load($attestationObjectRaw);
        } catch (Throwable $e) {
            throw new WebAuthnCeremonyException('AttestationObject corrompido ou formato não suportado.', previous: $e);
        }

        $authData = $attestationObject->authData;

        // 5. Validação de RP ID Hash
        $expectedRpId = (string) (parse_url($appUrl, PHP_URL_HOST) ?? 'localhost');
        $expectedRpIdHash = hash('sha256', $expectedRpId, true);

        if (! hash_equals($expectedRpIdHash, $authData->rpIdHash)) {
            throw new WebAuthnCeremonyException('RP ID hash não confere com o domínio esperado.');
        }

        // 6. User Verification mandatório
        if (! $authData->isUserVerified()) {
            throw new WebAuthnCeremonyException('A validação de usuário (biometria ou PIN) é estritamente obrigatória.');
        }

        // 7. Dados de credencial atestada
        $attestedCredentialData = $authData->attestedCredentialData;
        if ($attestedCredentialData === null) {
            throw new WebAuthnCeremonyException('Credencial não contém dados atestados.');
        }

        $credentialId = Base64UrlSafe::encodeUnpadded($attestedCredentialData->credentialId);

        if (Passkey::where('credential_id', $credentialId)->exists()) {
            throw new WebAuthnCeremonyException('Esta credencial já está cadastrada no sistema.');
        }

        $coseKey = $attestedCredentialData->credentialPublicKey;
        $publicKey = is_string($coseKey) ? Base64UrlSafe::encodeUnpadded($coseKey) : Base64UrlSafe::encodeUnpadded((string) $coseKey);
        $aaguid = $attestedCredentialData->aaguid->toString();
        $transports = $payload['response']['transports'] ?? [];
        $flagsByte = ord($authData->flags);
        $backupEligible = ($flagsByte & AuthenticatorData::FLAG_BE) !== 0;

        return Passkey::create([
            'user_id' => $user->id,
            'name' => trim($name) !== '' ? trim($name) : 'Minha Passkey',
            'credential_id' => $credentialId,
            'public_key' => $publicKey,
            'sign_count' => $authData->signCount,
            'transports' => is_array($transports) ? $transports : [],
            'aaguid' => $aaguid !== '00000000-0000-0000-0000-000000000000' ? $aaguid : null,
            'backup_eligible' => $backupEligible,
            'last_used_at' => null,
        ]);
    }
}
