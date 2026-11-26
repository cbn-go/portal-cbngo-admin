<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportOpinionMember;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use App\Notifications\JubanReportFiscalReviewResultNotification;
use App\Services\FiscalCouncil\FiscalCouncilOpinionSigningService;
use App\Services\Juban\Exceptions\JubanSigningException;
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

class FiscalCouncilOpinionSigningServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCouncilOpinionSigningService $service;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.url' => 'https://cbngo.org.br',
            'app.name' => 'CBN-GO',
        ]);

        Storage::fake('local');
        $this->service = app(FiscalCouncilOpinionSigningService::class);
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

    private function buildPayload(array $keyData, string $challengeBinary, int $signCount = 1, bool $withUv = true): array
    {
        $clientDataJSON = json_encode([
            'type' => 'webauthn.get',
            'challenge' => Base64UrlSafe::encodeUnpadded($challengeBinary),
            'origin' => 'https://cbngo.org.br',
        ], JSON_THROW_ON_ERROR);

        $rpIdHash = hash('sha256', 'cbngo.org.br', true);
        $flagsByte = chr(AuthenticatorData::FLAG_UP | ($withUv ? AuthenticatorData::FLAG_UV : 0));
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

    private function createReportAndOpinion(
        JubanReportOpinionResult $result = JubanReportOpinionResult::APROVADO,
        JubanReportOpinionStatus $opinionStatus = JubanReportOpinionStatus::ISSUED
    ): array {
        $uRelator = User::factory()->create(['name' => 'Abílio Rocha', 'role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        $uVogal1 = User::factory()->create(['name' => 'Juarez Araújo', 'role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        $uVogal2 = User::factory()->create(['name' => 'Marilane Jacintho', 'role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);

        $mRelator = FiscalCouncilMember::factory()->create([
            'user_id' => $uRelator->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);
        $mVogal1 = FiscalCouncilMember::factory()->create([
            'user_id' => $uVogal1->id,
            'seat' => FiscalCouncilSeat::VOGAL_1,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);
        $mVogal2 = FiscalCouncilMember::factory()->create([
            'user_id' => $uVogal2->id,
            'seat' => FiscalCouncilSeat::SUPLENTE_1,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        $reportPdf = '%PDF-report-content';
        $reportPath = 'juban-reports/2026-q1-report.pdf';
        Storage::disk('local')->put($reportPath, $reportPdf);

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'status' => JubanReportStatus::EM_REVISAO,
            'pdf_path' => $reportPath,
            'pdf_sha256' => hash('sha256', $reportPdf),
        ]);

        $opinionPdf = '%PDF-opinion-content';
        $opinionPath = 'juban-opinions/2026-q1-opinion.pdf';
        if ($opinionStatus === JubanReportOpinionStatus::ISSUED) {
            Storage::disk('local')->put($opinionPath, $opinionPdf);
        }

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => $result,
            'status' => $opinionStatus,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da CBN-GO',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $uRelator->id,
            'issued_at' => $opinionStatus === JubanReportOpinionStatus::ISSUED ? now() : null,
            'pdf_path' => $opinionStatus === JubanReportOpinionStatus::ISSUED ? $opinionPath : null,
            'pdf_sha256' => $opinionStatus === JubanReportOpinionStatus::ISSUED ? hash('sha256', $opinionPdf) : null,
            'reservations' => $result->requiresReservations() ? 'Observações e ressalvas' : null,
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal1->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        return [
            'report' => $report,
            'opinion' => $opinion,
            'relator' => ['user' => $uRelator, 'member' => $mRelator],
            'vogal1' => ['user' => $uVogal1, 'member' => $mVogal1],
            'vogal2' => ['user' => $uVogal2, 'member' => $mVogal2],
        ];
    }

    public function test_generate_signing_options_returns_canonical_challenge(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];
        $user = $setup['relator']['user'];

        $keyData = $this->createPasskeyWithKey($user);

        $options = $this->service->generateSigningOptions($opinion, $user);

        $this->assertArrayHasKey('challenge', $options);
        $this->assertArrayHasKey('rpId', $options);
        $this->assertSame('cbngo.org.br', $options['rpId']);
        $this->assertSame('required', $options['userVerification']);
        $this->assertNotEmpty($options['allowCredentials']);
    }

    public function test_cannot_generate_signing_options_if_opinion_not_issued(): void
    {
        $setup = $this->createReportAndOpinion(opinionStatus: JubanReportOpinionStatus::DRAFT);
        $opinion = $setup['opinion'];
        $user = $setup['relator']['user'];

        $this->createPasskeyWithKey($user);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('O parecer fiscal deve estar emitido para ser assinado.');

        $this->service->generateSigningOptions($opinion, $user);
    }

    public function test_cannot_sign_if_user_not_indicated_in_opinion(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];

        $otherUser = User::factory()->create(['name' => 'Outro Conselheiro', 'role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $otherUser->id,
            'seat' => FiscalCouncilSeat::SUPLENTE_2,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        $this->createPasskeyWithKey($otherUser);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('não foi indicado para assinar este parecer');

        $this->service->generateSigningOptions($opinion, $otherUser);
    }

    public function test_cannot_sign_if_term_expired(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];
        $user = $setup['vogal1']['user'];
        $member = $setup['vogal1']['member'];

        $member->update([
            'term_ends_on' => '2025-12-31', // vencido
        ]);

        $this->createPasskeyWithKey($user);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('não possui mandato ativo');

        $this->service->generateSigningOptions($opinion, $user);
    }

    public function test_cannot_sign_if_already_signed_juban_report_as_treasurer_or_president(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];
        $report = $setup['report'];
        $user = $setup['vogal1']['user'];

        $keyData = $this->createPasskeyWithKey($user);

        // Usuário já assinou como tesouraria do relatório JUBAN
        JubanReportSignature::query()->create([
            'report_id' => $report->id,
            'user_id' => $user->id,
            'role' => JubanSigningRole::TESOURARIA,
            'passkey_id' => $keyData['passkey']->id,
            'nonce' => bin2hex(random_bytes(32)),
            'challenge' => bin2hex(random_bytes(32)),
            'client_data_json' => '{}',
            'authenticator_data' => 'dummy',
            'signature' => 'dummy',
            'pdf_sha256' => $report->pdf_sha256,
            'signed_at' => now(),
        ]);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('já assinou este relatório pela Tesouraria ou Presidência');

        $this->service->generateSigningOptions($opinion, $user);
    }

    public function test_suplente_indicated_as_vogal_can_sign_normally(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];
        $user = $setup['vogal2']['user']; // Suplente 1 indicado como Vogal 2

        $keyData = $this->createPasskeyWithKey($user);

        $options = $this->service->generateSigningOptions($opinion, $user);

        $this->assertNotEmpty($options['challenge']);
        $this->assertSame(JubanSigningRole::CONSELHO_VOGAL_2, $this->service->determineSigningRole($opinion, $user));
    }

    public function test_verify_and_sign_with_valid_passkey_records_signature(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];
        $user = $setup['relator']['user'];

        $keyData = $this->createPasskeyWithKey($user);
        $options = $this->service->generateSigningOptions($opinion, $user);

        $challengeBinary = Base64UrlSafe::decodeNoPadding($options['challenge']);
        $payload = $this->buildPayload($keyData, $challengeBinary);

        $signature = $this->service->verifyAndSign($opinion, $user, $payload);

        $this->assertInstanceOf(JubanReportSignature::class, $signature);
        $this->assertEquals($opinion->id, $signature->opinion_id);
        $this->assertEquals($opinion->report_id, $signature->report_id);
        $this->assertEquals(JubanSigningRole::CONSELHO_RELATOR, $signature->role);
        $this->assertNull($signature->divergent_vote);

        $this->assertDatabaseHas('juban_report_signatures', [
            'opinion_id' => $opinion->id,
            'role' => JubanSigningRole::CONSELHO_RELATOR->value,
            'user_id' => $user->id,
        ]);
    }

    public function test_verify_and_sign_supports_divergent_vote(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];
        $user = $setup['vogal1']['user'];

        $keyData = $this->createPasskeyWithKey($user);
        $options = $this->service->generateSigningOptions($opinion, $user);

        $challengeBinary = Base64UrlSafe::decodeNoPadding($options['challenge']);
        $payload = $this->buildPayload($keyData, $challengeBinary);

        $divergentText = 'Voto com ressalva referente à conciliação da conta investimento.';
        $signature = $this->service->verifyAndSign($opinion, $user, $payload, divergentVote: $divergentText);

        $this->assertSame($divergentText, $signature->divergent_vote);
        $this->assertDatabaseHas('juban_report_signatures', [
            'opinion_id' => $opinion->id,
            'role' => JubanSigningRole::CONSELHO_VOGAL_1->value,
            'divergent_vote' => $divergentText,
        ]);
    }

    public function test_verify_and_sign_rejects_missing_uv_flag(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];
        $user = $setup['relator']['user'];

        $keyData = $this->createPasskeyWithKey($user);
        $options = $this->service->generateSigningOptions($opinion, $user);

        $challengeBinary = Base64UrlSafe::decodeNoPadding($options['challenge']);
        $payload = $this->buildPayload($keyData, $challengeBinary, withUv: false);

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('validação de usuário (biometria ou PIN) é estritamente obrigatória');

        $this->service->verifyAndSign($opinion, $user, $payload);
    }

    public function test_verify_and_sign_rejects_replayed_or_expired_challenge(): void
    {
        $setup = $this->createReportAndOpinion();
        $opinion = $setup['opinion'];
        $user = $setup['relator']['user'];

        $keyData = $this->createPasskeyWithKey($user);
        $payload = $this->buildPayload($keyData, random_bytes(32));

        $this->expectException(JubanSigningException::class);
        $this->expectExceptionMessage('Sessão de assinatura expirada ou inválida.');

        $this->service->verifyAndSign($opinion, $user, $payload);
    }

    public function test_completing_all_3_signatures_generates_signed_copy_and_transitions_report_status(): void
    {
        Notification::fake();

        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        $setup = $this->createReportAndOpinion(result: JubanReportOpinionResult::APROVADO_COM_RESSALVAS);
        $opinion = $setup['opinion'];
        $report = $setup['report'];

        $uRelator = $setup['relator']['user'];
        $uVogal1 = $setup['vogal1']['user'];
        $uVogal2 = $setup['vogal2']['user'];

        $kRelator = $this->createPasskeyWithKey($uRelator);
        $kVogal1 = $this->createPasskeyWithKey($uVogal1);
        $kVogal2 = $this->createPasskeyWithKey($uVogal2);

        $origOpinionSha = $opinion->pdf_sha256;

        // 1. Relator assina
        $optRelator = $this->service->generateSigningOptions($opinion, $uRelator);
        $this->service->verifyAndSign($opinion, $uRelator, $this->buildPayload($kRelator, Base64UrlSafe::decodeNoPadding($optRelator['challenge'])));

        $this->assertFalse($opinion->fresh()->isQuorumComplete());
        $this->assertSame(JubanReportStatus::EM_REVISAO, $report->fresh()->status);

        // 2. Vogal 1 assina
        $optVogal1 = $this->service->generateSigningOptions($opinion, $uVogal1);
        $this->service->verifyAndSign($opinion, $uVogal1, $this->buildPayload($kVogal1, Base64UrlSafe::decodeNoPadding($optVogal1['challenge'])));

        $this->assertFalse($opinion->fresh()->isQuorumComplete());

        // 3. Vogal 2 assina (completa quórum de 3)
        $optVogal2 = $this->service->generateSigningOptions($opinion, $uVogal2);
        $this->service->verifyAndSign($opinion, $uVogal2, $this->buildPayload($kVogal2, Base64UrlSafe::decodeNoPadding($optVogal2['challenge'])));

        $freshOpinion = $opinion->fresh();
        $this->assertTrue($freshOpinion->isQuorumComplete());
        $this->assertNotNull($freshOpinion->signed_pdf_path);
        $this->assertNotNull($freshOpinion->signed_pdf_sha256);
        $this->assertSame(64, strlen($freshOpinion->signed_pdf_sha256));

        // PDF original emitido permanece rigorosamente inalterado
        $this->assertSame($origOpinionSha, $freshOpinion->pdf_sha256);

        // Relatório JUBAN avança para o status do parecer (APROVADO_COM_RESSALVAS)
        $this->assertSame(JubanReportStatus::APROVADO_COM_RESSALVAS, $report->fresh()->status);

        // Liderança JUBAN notificada
        Notification::assertSentTo(
            [$treasurer, $president],
            JubanReportFiscalReviewResultNotification::class,
            fn ($n) => $n->resultStatus === JubanReportStatus::APROVADO_COM_RESSALVAS
        );
    }
}
