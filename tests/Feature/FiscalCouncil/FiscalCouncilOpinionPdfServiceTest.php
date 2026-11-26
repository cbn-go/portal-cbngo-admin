<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportInquiryStatus;
use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportOpinionMember;
use App\Models\User;
use App\Services\FiscalCouncil\FiscalCouncilOpinionPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiscalCouncilOpinionPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCouncilOpinionPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = app(FiscalCouncilOpinionPdfService::class);
    }

    public function test_render_pdf_contains_all_model_elements_and_footer(): void
    {
        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'pdf_sha256' => 'abc123reportsha256hashdummyvalue9876543210fedcba',
        ]);

        $uRelator = User::factory()->create(['name' => 'Abílio Rocha Neto']);
        $uVogal1 = User::factory()->create(['name' => 'Juarez Raimundo de Araújo']);
        $uVogal2 = User::factory()->create(['name' => 'Marilane Cristina Jacintho e Braga']);

        $mRelator = FiscalCouncilMember::factory()->create([
            'user_id' => $uRelator->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'title' => 'Pr.',
            'qualification' => 'Técnico em contabilidade',
        ]);
        $mVogal1 = FiscalCouncilMember::factory()->create([
            'user_id' => $uVogal1->id,
            'seat' => FiscalCouncilSeat::VOGAL_1,
            'title' => 'Dc.',
            'qualification' => 'Administrador',
        ]);
        $mVogal2 = FiscalCouncilMember::factory()->create([
            'user_id' => $uVogal2->id,
            'seat' => FiscalCouncilSeat::VOGAL_2,
            'title' => 'Dca.',
            'qualification' => 'Bacharel em Direito',
        ]);

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::DRAFT,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $uRelator->id,
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal1->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        JubanReportInquiry::query()->create([
            'report_id' => $report->id,
            'asked_by' => $uRelator->id,
            'asked_at' => now(),
            'question' => 'Comprovante do evento de liderança',
            'answer' => 'Anexado comprovante bancário',
            'status' => JubanReportInquiryStatus::RESPONDIDO,
            'answered_at' => now(),
        ]);

        $opinion->load(['report.inquiries', 'members.member.user']);

        $pdfBytes = $this->service->renderPdf($opinion);

        $this->assertNotEmpty($pdfBytes);
        $this->assertStringStartsWith('%PDF', $pdfBytes);

        $stored = $this->service->generateAndStore($opinion);

        $this->assertArrayHasKey('pdf_path', $stored);
        $this->assertArrayHasKey('pdf_sha256', $stored);
        Storage::disk('local')->assertExists($stored['pdf_path']);
        $this->assertSame($stored['pdf_sha256'], hash('sha256', Storage::disk('local')->get($stored['pdf_path'])));
    }
}
