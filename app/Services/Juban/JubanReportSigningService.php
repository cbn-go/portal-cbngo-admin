<?php

namespace App\Services\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Models\JubanReport;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Juban\Exceptions\JubanSigningException;
use CBOR\Decoder;
use CBOR\Normalizable;
use CBOR\StringStream;
use Cose\Algorithm\Manager;
use Cose\Algorithm\Signature\ECDSA\ES256;
use Cose\Algorithm\Signature\RSA\RS256;
use Cose\Algorithm\Signature\Signature;
use Cose\Key\Key;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;
use Webauthn\Util\CoseSignatureFixer;

class JubanReportSigningService
{
    public const CHALLENGE_TTL_SECONDS = 300; // 5 minutos

    /**
     * Gera os parâmetros e o challenge canônico para navigator.credentials.get().
     *
     * @return array<string, mixed>
     */
    public function generateSigningOptions(JubanReport $report, User $user, JubanSigningRole $role): array
    {
        // 1. Regra anti-duplicidade (segregação estrita: a mesma pessoa não assina nos dois papéis)
        if ($report->signatures()->where('user_id', $user->id)->exists()) {
            throw new JubanSigningException('A mesma pessoa não pode assinar nos dois papéis.');
        }

        // 2. Validação de ordem e permissões
        if ($role === JubanSigningRole::TESOURARIA) {
            if ($report->status !== JubanReportStatus::AGUARDANDO_TESOURARIA) {
                throw new JubanSigningException('O relatório não está aguardando a assinatura da Tesouraria.');
            }

            if (! $user->role->isJubanTreasurer() && ! $user->role->isSuperAdmin()) {
                throw new JubanSigningException('Usuário não tem permissão para assinar como Tesouraria.');
            }
        } elseif ($role === JubanSigningRole::PRESIDENCIA) {
            if ($report->status !== JubanReportStatus::AGUARDANDO_PRESIDENCIA) {
                throw new JubanSigningException('O relatório não está aguardando a assinatura da Presidência.');
            }

            if (! $user->role->isJubanPresident() && ! $user->role->isSuperAdmin()) {
                throw new JubanSigningException('Usuário não tem permissão para assinar como Presidência.');
            }
        }

        // 3. Validação de integridade do arquivo PDF físico gravado
        $this->assertPdfIntegrity($report);

        // 4. Geração do Nonce e Challenge canônico
        $nonce = bin2hex(random_bytes(32)); // 64 hex characters
        $canonicalInput = "cbngo:juban-report:v1|{$report->id}|{$report->pdf_sha256}|{$user->id}|{$role->value}|{$nonce}";
        $challengeHex = hash('sha256', $canonicalInput);
        $challengeBinary = hash('sha256', $canonicalInput, true);
        $challengeBase64Url = Base64UrlSafe::encodeUnpadded($challengeBinary);

        // 5. Armazena no Cache com TTL de 5 minutos
        Cache::put(
            "juban_signing_{$report->id}_{$user->id}",
            [
                'role' => $role->value,
                'nonce' => $nonce,
                'challenge_hex' => $challengeHex,
                'challenge_binary' => $challengeBinary,
                'pdf_sha256' => $report->pdf_sha256,
            ],
            self::CHALLENGE_TTL_SECONDS
        );

        $appUrl = (string) config('app.url', 'http://localhost');
        $rpId = (string) (parse_url($appUrl, PHP_URL_HOST) ?? 'localhost');

        $allowCredentials = $user->passkeys()
            ->get(['credential_id', 'transports'])
            ->map(fn (Passkey $p) => array_filter([
                'type' => 'public-key',
                'id' => $p->credential_id,
                'transports' => $p->transports,
            ]))
            ->values()
            ->all();

        return [
            'challenge' => $challengeBase64Url,
            'rpId' => $rpId,
            'allowCredentials' => $allowCredentials,
            'userVerification' => 'required',
            'timeout' => 60000,
        ];
    }

    /**
     * Valida a assinatura com WebAuthn, grava a trilha de auditoria e avança o status do relatório.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyAndSign(
        JubanReport $report,
        User $user,
        JubanSigningRole $role,
        array $payload,
        ?string $ip = null,
        ?string $userAgent = null
    ): JubanReportSignature {
        // 1. Consome o challenge do cache (uso único)
        $cached = Cache::pull("juban_signing_{$report->id}_{$user->id}");
        if (! is_array($cached) || ($cached['role'] ?? null) !== $role->value) {
            throw new JubanSigningException('Sessão de assinatura expirada ou inválida.');
        }

        // 2. Re-validação da integridade do PDF
        $this->assertPdfIntegrity($report);

        if ($cached['pdf_sha256'] !== $report->pdf_sha256) {
            throw new JubanSigningException('O arquivo PDF do relatório foi alterado durante a cerimônia.');
        }

        // 3. Localiza e valida a Passkey do usuário
        $credentialId = $payload['id'] ?? $payload['rawId'] ?? null;
        if (! is_string($credentialId)) {
            throw new JubanSigningException('Identificador de credencial não fornecido.');
        }

        $passkey = Passkey::where('credential_id', $credentialId)->first();
        if (! $passkey || $passkey->user_id !== $user->id) {
            throw new JubanSigningException('A passkey utilizada não pertence ao usuário autenticado.');
        }

        // 4. Validação de clientDataJSON
        $clientDataJSONRaw = $this->extractAndDecodeBase64($payload['response']['clientDataJSON'] ?? null, 'clientDataJSON');
        $clientData = json_decode($clientDataJSONRaw, true);

        if (! is_array($clientData) || ($clientData['type'] ?? null) !== 'webauthn.get') {
            throw new JubanSigningException('Tipo de operação inválido.');
        }

        try {
            $receivedChallengeBinary = Base64UrlSafe::decodeNoPadding($clientData['challenge'] ?? '');
        } catch (Throwable $e) {
            throw new JubanSigningException('Challenge inválido em clientDataJSON.', previous: $e);
        }

        if (! hash_equals($cached['challenge_binary'], $receivedChallengeBinary)) {
            throw new JubanSigningException('Challenge não confere ou expirou.');
        }

        $appUrl = (string) config('app.url', 'http://localhost');
        $expectedOrigin = rtrim($appUrl, '/');
        $receivedOrigin = rtrim((string) ($clientData['origin'] ?? ''), '/');

        if ($receivedOrigin !== $expectedOrigin) {
            throw new JubanSigningException("Origem não autorizada: {$receivedOrigin}");
        }

        // 5. Validação de authenticatorData e flags (User Verification mandatório)
        $authDataRaw = $this->extractAndDecodeBase64($payload['response']['authenticatorData'] ?? null, 'authenticatorData');
        if (strlen($authDataRaw) < 37) {
            throw new JubanSigningException('authenticatorData corrompido ou incompleto.');
        }

        $expectedRpId = (string) (parse_url($appUrl, PHP_URL_HOST) ?? 'localhost');
        $expectedRpIdHash = hash('sha256', $expectedRpId, true);
        $rpIdHash = substr($authDataRaw, 0, 32);

        if (! hash_equals($expectedRpIdHash, $rpIdHash)) {
            throw new JubanSigningException('RP ID hash não confere com o domínio esperado.');
        }

        $flagsByte = ord($authDataRaw[32]);
        $userVerified = ($flagsByte & 0x04) !== 0; // bit 2 = UV

        if (! $userVerified) {
            throw new JubanSigningException('A validação de usuário (biometria ou PIN) é estritamente obrigatória.');
        }

        /** @var array{1: int} $unpack */
        $unpack = unpack('N', substr($authDataRaw, 33, 4));
        $newSignCount = $unpack[1];

        // 6. Verificação criptográfica da assinatura via webauthn-lib
        $this->verifyWebAuthnSignature($authDataRaw, $clientDataJSONRaw, $payload['response']['signature'] ?? null, $passkey);

        // 7. Atualiza sign_count da passkey
        $passkey->update([
            'sign_count' => $newSignCount,
            'last_used_at' => now(),
        ]);

        // 8. Persiste a assinatura e atualiza status do relatório
        return DB::transaction(function () use ($report, $user, $role, $passkey, $cached, $clientDataJSONRaw, $payload, $ip, $userAgent): JubanReportSignature {
            $signature = JubanReportSignature::create([
                'report_id' => $report->id,
                'user_id' => $user->id,
                'role' => $role,
                'passkey_id' => $passkey->id,
                'nonce' => $cached['nonce'],
                'challenge' => $cached['challenge_hex'],
                'client_data_json' => $clientDataJSONRaw,
                'authenticator_data' => (string) $payload['response']['authenticatorData'],
                'signature' => (string) $payload['response']['signature'],
                'pdf_sha256' => $cached['pdf_sha256'],
                'signed_at' => now(),
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            if ($role === JubanSigningRole::TESOURARIA) {
                $report->update(['status' => JubanReportStatus::AGUARDANDO_PRESIDENCIA]);
            } elseif ($role === JubanSigningRole::PRESIDENCIA) {
                $report->update(['status' => JubanReportStatus::ASSINADO]);
            }

            return $signature;
        });
    }

    /**
     * Valida se o arquivo PDF existe e se o hash físico bate com o registrado no relatório.
     */
    private function assertPdfIntegrity(JubanReport $report): void
    {
        if (! $report->pdf_path || ! Storage::disk('local')->exists($report->pdf_path)) {
            throw new JubanSigningException('O arquivo PDF do relatório não foi encontrado.');
        }

        $realPath = Storage::disk('local')->path($report->pdf_path);
        $computedHash = hash_file('sha256', $realPath);

        if (! hash_equals((string) $report->pdf_sha256, (string) $computedHash)) {
            throw new JubanSigningException('O arquivo PDF do relatório foi alterado ou corrompido.');
        }
    }

    private function extractAndDecodeBase64(mixed $encoded, string $fieldName): string
    {
        if (! is_string($encoded) || trim($encoded) === '') {
            throw new JubanSigningException("Campo {$fieldName} ausente na resposta.");
        }

        try {
            return Base64UrlSafe::decodeNoPadding($encoded);
        } catch (Throwable $e) {
            throw new JubanSigningException("Campo {$fieldName} contém base64url inválido.", previous: $e);
        }
    }

    /**
     * Valida a assinatura da asserção com webauthn-lib.
     */
    private function verifyWebAuthnSignature(
        string $authDataRaw,
        string $clientDataJSONRaw,
        mixed $signatureEncoded,
        Passkey $passkey
    ): void {
        $rawSignature = $this->extractAndDecodeBase64($signatureEncoded, 'signature');

        try {
            $coseKeyBytes = Base64UrlSafe::decodeNoPadding($passkey->public_key);
            $stream = new StringStream($coseKeyBytes);
            $cborObject = Decoder::create()->decode($stream);
            if (! $cborObject instanceof Normalizable) {
                throw new JubanSigningException('Objeto CBOR da chave não é normalizável.');
            }
            /** @var array<int|string, mixed> $normalizedKey */
            $normalizedKey = $cborObject->normalize();
            $key = Key::create($normalizedKey);

            $algorithmManager = Manager::create()
                ->add(ES256::create())
                ->add(RS256::create());

            $algorithm = $algorithmManager->get($key->alg());
            if (! $algorithm instanceof Signature) {
                throw new JubanSigningException('Algoritmo de assinatura não suportado.');
            }

            $fixedSignature = CoseSignatureFixer::fix($rawSignature, $algorithm);
            $dataToVerify = $authDataRaw.hash('sha256', $clientDataJSONRaw, true);

            if (! $algorithm->verify($dataToVerify, $key, $fixedSignature)) {
                throw new JubanSigningException('Assinatura WebAuthn inválida para a chave cadastrada.');
            }
        } catch (Throwable $e) {
            if ($e instanceof JubanSigningException) {
                throw $e;
            }
            throw new JubanSigningException('Falha na verificação da assinatura WebAuthn: '.$e->getMessage(), previous: $e);
        }
    }
}
