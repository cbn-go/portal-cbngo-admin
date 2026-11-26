<?php

namespace Tests\Feature\Juban;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\Passkey;
use App\Models\User;
use App\Notifications\JubanReportArrivedToFiscalCouncilNotification;
use App\Services\Juban\JubanReportSigningService;
use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\UnsignedIntegerObject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use ParagonIE\ConstantTime\Base64UrlSafe;
use Tests\TestCase;
use Webauthn\AuthenticatorData;

class JubanReportSigningToFiscalCouncilTest extends TestCase
{
    use RefreshDatabase;

    private function createPasskeyForUser(User $user): array
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

    private function buildWebAuthnPayload(array $keyData, string $challengeBinary): array
    {
        $clientDataJSON = json_encode([
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($challengeBinary),
            'origin' => config('app.url'),
        ], JSON_THROW_ON_ERROR);

        $rpId = (string) (parse_url(config('app.url'), PHP_URL_HOST) ?? 'localhost');
        $rpIdHash = hash('sha256', $rpId, true);
        $flagsByte = chr(AuthenticatorData::FLAG_UP | AuthenticatorData::FLAG_UV);
        $signCountBytes = pack('N', 1);
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

    public function test_presidency_signing_advances_status_to_aguardando_conselho_fiscal_and_notifies_members(): void
    {
        Notification::fake();
        Storage::fake('local');

        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        // Membro ativo do Conselho Fiscal
        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'term_starts_on' => now()->subMonth(),
            'term_ends_on' => now()->addYears(2),
            'active' => true,
        ]);

        $pdfBytes = '%PDF-1.4 Fake PDF Content for Juban Signing';
        $pdfPath = 'juban-reports/2026-q1-v1-relatorio.pdf';
        Storage::disk('local')->put($pdfPath, $pdfBytes);
        $pdfSha256 = hash('sha256', $pdfBytes);

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
            'pdf_path' => $pdfPath,
            'pdf_sha256' => $pdfSha256,
        ]);

        $service = app(JubanReportSigningService::class);

        // 1. Assinatura do Tesoureiro
        $treasurerKey = $this->createPasskeyForUser($treasurer);
        $optionsT = $service->generateSigningOptions($report, $treasurer, JubanSigningRole::TESOURARIA);
        $payloadT = $this->buildWebAuthnPayload($treasurerKey, Base64UrlSafe::decodeNoPadding($optionsT['challenge']));
        $service->verifyAndSign($report, $treasurer, JubanSigningRole::TESOURARIA, $payloadT, '127.0.0.1', 'PHPUnit');

        $this->assertEquals(JubanReportStatus::AGUARDANDO_PRESIDENCIA, $report->fresh()->status);
        Notification::assertNothingSent();

        // 2. Assinatura do Presidente
        $presidentKey = $this->createPasskeyForUser($president);
        $optionsP = $service->generateSigningOptions($report->fresh(), $president, JubanSigningRole::PRESIDENCIA);
        $payloadP = $this->buildWebAuthnPayload($presidentKey, Base64UrlSafe::decodeNoPadding($optionsP['challenge']));
        $service->verifyAndSign($report->fresh(), $president, JubanSigningRole::PRESIDENCIA, $payloadP, '127.0.0.1', 'PHPUnit');

        $report->refresh();
        $this->assertEquals(JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL, $report->status);

        // Notificação enviada ao conselheiro ativo
        Notification::assertSentTo($fiscalUser, JubanReportArrivedToFiscalCouncilNotification::class);
    }
}
