<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportOpinionMember;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use App\Services\FiscalCouncil\FiscalCouncilSignedOpinionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiscalCouncilSignedOpinionPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCouncilSignedOpinionPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = app(FiscalCouncilSignedOpinionPdfService::class);
    }

    public function test_generate_signed_copy_includes_manifest_page_and_preserves_original(): void
    {
        $uRelator = User::factory()->create(['name' => 'Abílio Rocha', 'role' => UserRole::FISCAL_COUNCIL]);
        $uVogal1 = User::factory()->create(['name' => 'Juarez Araújo', 'role' => UserRole::FISCAL_COUNCIL]);
        $uVogal2 = User::factory()->create(['name' => 'Marilane Jacintho', 'role' => UserRole::FISCAL_COUNCIL]);

        $mRelator = FiscalCouncilMember::factory()->create(['user_id' => $uRelator->id, 'seat' => FiscalCouncilSeat::RELATOR, 'title' => 'Pr.', 'qualification' => 'Contador']);
        $mVogal1 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal1->id, 'seat' => FiscalCouncilSeat::VOGAL_1, 'title' => 'Dc.', 'qualification' => 'Administrador']);
        $mVogal2 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal2->id, 'seat' => FiscalCouncilSeat::VOGAL_2, 'title' => 'Dca.', 'qualification' => 'Advogada']);

        $reportPath = 'juban-reports/2026-q1.pdf';
        Storage::disk('local')->put($reportPath, '%PDF-report');
        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'pdf_path' => $reportPath,
            'pdf_sha256' => hash('sha256', '%PDF-report'),
        ]);

        $opinionPath = 'juban-opinions/2026-q1-opinion.pdf';
        Storage::disk('local')->put($opinionPath, '%PDF-opinion-emitted');
        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::ISSUED,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da CBN-GO',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $uRelator->id,
            'issued_at' => now(),
            'pdf_path' => $opinionPath,
            'pdf_sha256' => hash('sha256', '%PDF-opinion-emitted'),
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal1->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        $pRelator = Passkey::factory()->create(['user_id' => $uRelator->id, 'name' => 'MacBook TouchID']);
        $pVogal1 = Passkey::factory()->create(['user_id' => $uVogal1->id, 'name' => 'YubiKey 5C']);
        $pVogal2 = Passkey::factory()->create(['user_id' => $uVogal2->id, 'name' => 'Windows Hello']);

        JubanReportSignature::query()->create([
            'report_id' => $report->id,
            'opinion_id' => $opinion->id,
            'user_id' => $uRelator->id,
            'role' => JubanSigningRole::CONSELHO_RELATOR,
            'passkey_id' => $pRelator->id,
            'nonce' => bin2hex(random_bytes(32)),
            'challenge' => bin2hex(random_bytes(32)),
            'client_data_json' => '{}',
            'authenticator_data' => 'dummy',
            'signature' => 'dummy',
            'pdf_sha256' => $opinion->pdf_sha256,
            'signed_at' => now()->subMinutes(10),
        ]);

        JubanReportSignature::query()->create([
            'report_id' => $report->id,
            'opinion_id' => $opinion->id,
            'user_id' => $uVogal1->id,
            'role' => JubanSigningRole::CONSELHO_VOGAL_1,
            'passkey_id' => $pVogal1->id,
            'nonce' => bin2hex(random_bytes(32)),
            'challenge' => bin2hex(random_bytes(32)),
            'client_data_json' => '{}',
            'authenticator_data' => 'dummy',
            'signature' => 'dummy',
            'pdf_sha256' => $opinion->pdf_sha256,
            'signed_at' => now()->subMinutes(5),
            'divergent_vote' => 'Ressalvo o atraso na entrega dos documentos.',
        ]);

        JubanReportSignature::query()->create([
            'report_id' => $report->id,
            'opinion_id' => $opinion->id,
            'user_id' => $uVogal2->id,
            'role' => JubanSigningRole::CONSELHO_VOGAL_2,
            'passkey_id' => $pVogal2->id,
            'nonce' => bin2hex(random_bytes(32)),
            'challenge' => bin2hex(random_bytes(32)),
            'client_data_json' => '{}',
            'authenticator_data' => 'dummy',
            'signature' => 'dummy',
            'pdf_sha256' => $opinion->pdf_sha256,
            'signed_at' => now(),
        ]);

        $result = $this->service->generateSignedCopy($opinion);

        $this->assertArrayHasKey('signed_pdf_path', $result);
        $this->assertArrayHasKey('signed_pdf_sha256', $result);
        $this->assertNotSame($opinion->pdf_sha256, $result['signed_pdf_sha256']);

        Storage::disk('local')->assertExists($result['signed_pdf_path']);
        $bytes = Storage::disk('local')->get($result['signed_pdf_path']);
        $this->assertStringStartsWith('%PDF', $bytes);
        $this->assertSame($result['signed_pdf_sha256'], hash('sha256', $bytes));
    }
}
