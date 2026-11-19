<?php

namespace App\Services\Juban;

use App\Models\JubanReportSignature;
use App\Services\Juban\Exceptions\JubanSigningException;
use CBOR\Decoder;
use CBOR\Normalizable;
use CBOR\StringStream;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Throwable;

class IndependentSignatureVerifier
{
    /**
     * Valida de forma autocontida a assinatura do relatório sem utilizar webauthn-lib,
     * recompondo o challenge original e conferindo os bytes assinados com OpenSSL nativo.
     *
     * @return array{valid: bool, reason: ?string, details: array<string, mixed>}
     */
    public function verify(JubanReportSignature $signature): array
    {
        // 1. Recomposição canônica do challenge
        $canonicalInput = "cbngo:juban-report:v1|{$signature->report_id}|{$signature->pdf_sha256}|{$signature->user_id}|{$signature->role->value}|{$signature->nonce}";
        $expectedChallengeHex = hash('sha256', $canonicalInput);
        $expectedChallengeBinary = hash('sha256', $canonicalInput, true);

        if (! hash_equals($expectedChallengeHex, $signature->challenge)) {
            return [
                'valid' => false,
                'reason' => 'O challenge gravado não corresponde à fórmula canônica cbngo:juban-report:v1.',
                'details' => [
                    'expected' => $expectedChallengeHex,
                    'actual' => $signature->challenge,
                ],
            ];
        }

        // 2. Validação da estrutura e challenge de clientDataJSON
        $clientDataJSONRaw = $signature->client_data_json;
        $clientData = json_decode($clientDataJSONRaw, true);

        if (! is_array($clientData) || ! isset($clientData['type'], $clientData['challenge'])) {
            return [
                'valid' => false,
                'reason' => 'Estrutura de clientDataJSON inválida ou corrompida.',
                'details' => [],
            ];
        }

        if ($clientData['type'] !== 'webauthn.get') {
            return [
                'valid' => false,
                'reason' => 'Tipo de operação em clientDataJSON não é webauthn.get.',
                'details' => ['type' => $clientData['type']],
            ];
        }

        try {
            $receivedChallengeBinary = Base64UrlSafe::decodeNoPadding($clientData['challenge']);
        } catch (Throwable) {
            return [
                'valid' => false,
                'reason' => 'Challenge em clientDataJSON não é base64url válido.',
                'details' => [],
            ];
        }

        if (! hash_equals($expectedChallengeBinary, $receivedChallengeBinary)) {
            return [
                'valid' => false,
                'reason' => 'O challenge em clientDataJSON não corresponde ao esperado.',
                'details' => [],
            ];
        }

        // 3. Validação de authenticatorData e flag User Verification (UV)
        try {
            $authDataRaw = Base64UrlSafe::decodeNoPadding($signature->authenticator_data);
        } catch (Throwable) {
            return [
                'valid' => false,
                'reason' => 'authenticatorData não é base64url válido.',
                'details' => [],
            ];
        }

        if (strlen($authDataRaw) < 37) {
            return [
                'valid' => false,
                'reason' => 'authenticatorData corrompido ou incompleto.',
                'details' => ['length' => strlen($authDataRaw)],
            ];
        }

        // Offset 32: flags (1 byte). Bit 2 (0x04) = User Verification (UV)
        $flagsByte = ord($authDataRaw[32]);
        $userVerified = ($flagsByte & 0x04) !== 0;

        if (! $userVerified) {
            return [
                'valid' => false,
                'reason' => 'A flag de verificação de usuário (UV) não está ativa no authenticatorData.',
                'details' => ['flags' => $flagsByte],
            ];
        }

        // 4. Conversão da chave pública COSE para PEM padrão (SubjectPublicKeyInfo)
        $passkey = $signature->passkey;

        try {
            $cborBytes = Base64UrlSafe::decodeNoPadding($passkey->public_key);
            $stream = new StringStream($cborBytes);
            $cborObject = Decoder::create()->decode($stream);
            if (! $cborObject instanceof Normalizable) {
                throw new JubanSigningException('Objeto CBOR da chave não é normalizável.');
            }
            $normalized = $cborObject->normalize();
            $publicKeyPem = $this->convertCoseToPem($normalized);
        } catch (Throwable $e) {
            return [
                'valid' => false,
                'reason' => 'Não foi possível converter a chave pública da passkey: '.$e->getMessage(),
                'details' => [],
            ];
        }

        // 5. Verificação da assinatura criptográfica com OpenSSL
        try {
            $rawSignature = Base64UrlSafe::decodeNoPadding($signature->signature);
        } catch (Throwable) {
            return [
                'valid' => false,
                'reason' => 'Assinatura não é base64url válida.',
                'details' => [],
            ];
        }

        $dataToVerify = $authDataRaw.hash('sha256', $clientDataJSONRaw, true);
        $verifyResult = openssl_verify($dataToVerify, $rawSignature, $publicKeyPem, OPENSSL_ALGO_SHA256);

        if ($verifyResult !== 1) {
            return [
                'valid' => false,
                'reason' => 'Assinatura criptográfica inválida para a chave pública fornecida.',
                'details' => ['openssl_result' => $verifyResult],
            ];
        }

        return [
            'valid' => true,
            'reason' => null,
            'details' => [
                'reconstructed_challenge' => $expectedChallengeHex,
                'user_verified' => true,
                'algorithm' => 'ES256',
                'signed_at' => $signature->signed_at->toIso8601String(),
                'pdf_sha256' => $signature->pdf_sha256,
            ],
        ];
    }

    /**
     * Converte os parâmetros normalizados de uma COSE Key em formato PEM SubjectPublicKeyInfo.
     */
    private function convertCoseToPem(mixed $coseNormalized): string
    {
        if (! is_array($coseNormalized)) {
            throw new JubanSigningException('Formato de chave COSE inválido.');
        }

        $kty = isset($coseNormalized[1]) ? (int) $coseNormalized[1] : null;

        // EC2 key (kty = 2, alg = -7 ES256, crv = 1 P-256)
        if ($kty === 2) {
            $x = $coseNormalized[-2] ?? null;
            $y = $coseNormalized[-3] ?? null;

            if (! is_string($x) || ! is_string($y) || strlen($x) !== 32 || strlen($y) !== 32) {
                throw new JubanSigningException('Coordenadas EC inválidas na chave pública.');
            }

            // DER Header para id-ecPublicKey com namedCurve prime256v1 (26 bytes)
            $derHeader = hex2bin('3059301306072a8648ce3d020106082a8648ce3d03010703420004');
            $der = $derHeader.$x.$y;

            return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode((string) $der), 64, "\n")."-----END PUBLIC KEY-----\n";
        }

        throw new JubanSigningException("Tipo de chave COSE não suportado: {$kty}");
    }
}
