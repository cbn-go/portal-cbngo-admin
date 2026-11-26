<?php

namespace App\Services\FiscalCouncil;

use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use App\Notifications\JubanReportFiscalReviewResultNotification;
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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;
use Webauthn\Util\CoseSignatureFixer;

class FiscalCouncilOpinionSigningService
{
    public const CHALLENGE_TTL_SECONDS = 300; // 5 minutos

    public function __construct(
        protected FiscalCouncilSignedOpinionPdfService $signedPdfService
    ) {}

    /**
     * Determina o papel do usuário no parecer fiscal baseado na posição cadastrada.
     */
    public function determineSigningRole(JubanReportOpinion $opinion, User $user): JubanSigningRole
    {
        $opinion->loadMissing('members.member');

        $opinionMember = $opinion->members->first(fn ($m) => $m->member->user_id === $user->id);

        if (! $opinionMember) {
            throw new JubanSigningException("O usuário {$user->name} não foi indicado para assinar este parecer fiscal.");
        }

        return match ($opinionMember->position) {
            1 => JubanSigningRole::CONSELHO_RELATOR,
            2 => JubanSigningRole::CONSELHO_VOGAL_1,
            3 => JubanSigningRole::CONSELHO_VOGAL_2,
            default => throw new JubanSigningException("Posição de assinatura inválida: {$opinionMember->position}"),
        };
    }

    /**
     * Gera os parâmetros e o challenge canônico para navigator.credentials.get().
     *
     * @return array<string, mixed>
     */
    public function generateSigningOptions(JubanReportOpinion $opinion, User $user): array
    {
        // 1. Validação do status do parecer
        if (! $opinion->isIssued()) {
            throw new JubanSigningException('O parecer fiscal deve estar emitido para ser assinado.');
        }

        // 2. Segregação de funções: quem assinou como Tesouraria ou Presidência não pode votar no Conselho Fiscal
        if ($opinion->report->signatures()->where('user_id', $user->id)->whereNull('opinion_id')->exists()) {
            throw new JubanSigningException("O usuário {$user->name} já assinou este relatório pela Tesouraria ou Presidência e não pode votar no Conselho Fiscal.");
        }

        // 3. Determina o papel e confere se foi indicado
        $role = $this->determineSigningRole($opinion, $user);

        // 4. Validação de mandato ativo
        $fiscalMember = FiscalCouncilMember::where('user_id', $user->id)->first();
        if (! $fiscalMember || ! $fiscalMember->active || ! $fiscalMember->isTermActive()) {
            throw new JubanSigningException("O membro {$user->name} não possui mandato ativo no Conselho Fiscal.");
        }

        // 5. Regra anti-duplicidade no parecer
        if ($opinion->signatures()->where('user_id', $user->id)->exists()) {
            throw new JubanSigningException("O usuário {$user->name} já assinou este parecer fiscal.");
        }

        // 6. Integridade dos PDFs físicos gravados
        $this->assertPdfIntegrity($opinion);

        // 7. Geração do Nonce e Challenge canônico
        $nonce = bin2hex(random_bytes(32)); // 64 hex characters
        $canonicalInput = "cbngo:juban-opinion:v1|{$opinion->report_id}|{$opinion->report->pdf_sha256}|{$opinion->id}|{$opinion->pdf_sha256}|{$user->id}|{$role->value}|{$nonce}";
        $challengeHex = hash('sha256', $canonicalInput);
        $challengeBinary = hash('sha256', $canonicalInput, true);
        $challengeBase64Url = Base64UrlSafe::encodeUnpadded($challengeBinary);

        // 8. Armazena no Cache com TTL de 5 minutos
        Cache::put(
            "juban_opinion_signing_{$opinion->id}_{$user->id}",
            [
                'role' => $role->value,
                'nonce' => $nonce,
                'challenge_hex' => $challengeHex,
                'challenge_binary' => $challengeBinary,
                'opinion_pdf_sha256' => $opinion->pdf_sha256,
                'report_pdf_sha256' => $opinion->report->pdf_sha256,
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
     * Valida a assinatura com WebAuthn, grava a trilha de auditoria e avança status caso o quórum seja atingido.
     *
     * @param  array<string, mixed>  $payload
     */
    public function verifyAndSign(
        JubanReportOpinion $opinion,
        User $user,
        array $payload,
        ?string $divergentVote = null,
        ?string $ip = null,
        ?string $userAgent = null
    ): JubanReportSignature {
        // 1. Consome o challenge do cache (uso único)
        $cached = Cache::pull("juban_opinion_signing_{$opinion->id}_{$user->id}");
        if (! is_array($cached)) {
            throw new JubanSigningException('Sessão de assinatura expirada ou inválida.');
        }

        // 2. Re-validação da integridade dos PDFs
        $this->assertPdfIntegrity($opinion);

        if ($cached['opinion_pdf_sha256'] !== $opinion->pdf_sha256 || $cached['report_pdf_sha256'] !== $opinion->report->pdf_sha256) {
            throw new JubanSigningException('Os arquivos foram alterados durante a cerimônia.');
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

        // 8. Persiste a assinatura e verifica quórum
        return DB::transaction(function () use ($opinion, $user, $passkey, $cached, $clientDataJSONRaw, $payload, $divergentVote, $ip, $userAgent): JubanReportSignature {
            $role = JubanSigningRole::from($cached['role']);

            $signature = JubanReportSignature::create([
                'report_id' => $opinion->report_id,
                'opinion_id' => $opinion->id,
                'user_id' => $user->id,
                'role' => $role,
                'passkey_id' => $passkey->id,
                'nonce' => $cached['nonce'],
                'challenge' => $cached['challenge_hex'],
                'client_data_json' => $clientDataJSONRaw,
                'authenticator_data' => (string) $payload['response']['authenticatorData'],
                'signature' => (string) $payload['response']['signature'],
                'divergent_vote' => $divergentVote ? trim($divergentVote) : null,
                'pdf_sha256' => $cached['opinion_pdf_sha256'],
                'signed_at' => now(),
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);

            // Se atingiu o quórum de 3 assinaturas (Relator + 2 Vogais)
            if ($opinion->signatures()->count() >= 3) {
                // 1. Gera e grava a Via Assinada com folha de manifesto
                $signedCopy = $this->signedPdfService->generateSignedCopy($opinion);
                $opinion->update([
                    'signed_pdf_path' => $signedCopy['signed_pdf_path'],
                    'signed_pdf_sha256' => $signedCopy['signed_pdf_sha256'],
                ]);

                // 2. Transiciona o status do relatório JUBAN de acordo com o resultado do parecer
                $newReportStatus = match ($opinion->result) {
                    JubanReportOpinionResult::APROVADO => JubanReportStatus::APROVADO,
                    JubanReportOpinionResult::APROVADO_COM_RESSALVAS => JubanReportStatus::APROVADO_COM_RESSALVAS,
                    JubanReportOpinionResult::REPROVADO => JubanReportStatus::REPROVADO,
                };

                $opinion->report->update(['status' => $newReportStatus]);

                // 3. Notifica a liderança da JUBAN (Tesouraria e Presidência)
                $leadershipUsers = User::query()
                    ->whereIn('role', [UserRole::JUBAN_TREASURER, UserRole::JUBAN_PRESIDENT])
                    ->where('is_active', true)
                    ->get();

                if ($leadershipUsers->isNotEmpty()) {
                    Notification::send($leadershipUsers, new JubanReportFiscalReviewResultNotification($opinion->report, $newReportStatus));
                }
            }

            return $signature;
        });
    }

    /**
     * Valida se os arquivos PDF existem e se seus hashes físicos batem com os registrados.
     */
    private function assertPdfIntegrity(JubanReportOpinion $opinion): void
    {
        if (! $opinion->pdf_path || ! Storage::disk('local')->exists($opinion->pdf_path)) {
            throw new JubanSigningException('O arquivo PDF do parecer fiscal não foi encontrado.');
        }

        $opinionRealPath = Storage::disk('local')->path($opinion->pdf_path);
        $computedOpinionHash = hash_file('sha256', $opinionRealPath);

        if (! hash_equals((string) $opinion->pdf_sha256, (string) $computedOpinionHash)) {
            throw new JubanSigningException('O arquivo PDF do parecer fiscal foi alterado ou corrompido.');
        }

        $report = $opinion->report;
        if (! $report->pdf_path || ! Storage::disk('local')->exists($report->pdf_path)) {
            throw new JubanSigningException('O arquivo PDF do relatório não foi encontrado.');
        }

        $reportRealPath = Storage::disk('local')->path($report->pdf_path);
        $computedReportHash = hash_file('sha256', $reportRealPath);

        if (! hash_equals((string) $report->pdf_sha256, (string) $computedReportHash)) {
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
