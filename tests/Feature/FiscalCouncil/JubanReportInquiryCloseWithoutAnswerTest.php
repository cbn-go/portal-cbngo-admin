<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportInquiryStatus;
use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\User;
use App\Services\FiscalCouncil\Exceptions\FiscalCouncilReviewException;
use App\Services\FiscalCouncil\JubanReportInquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JubanReportInquiryCloseWithoutAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_relator_can_close_inquiry_without_answer(): void
    {
        $relatorUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $relatorUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);
        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $relatorUser->id,
            'question' => 'Dúvida que foi sanada presencialmente',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $service = app(JubanReportInquiryService::class);
        $closed = $service->closeWithoutAnswer(
            $inquiry,
            $relatorUser,
            'Esclarecido em reunião com a diretoria sem necessidade de juntada documental.'
        );

        $this->assertEquals(JubanReportInquiryStatus::ENCERRADO_SEM_RESPOSTA, $closed->status);
        $this->assertEquals($relatorUser->id, $closed->closed_without_answer_by);
        $this->assertNotNull($closed->closed_without_answer_at);
        $this->assertEquals('Esclarecido em reunião com a diretoria sem necessidade de juntada documental.', $closed->closed_without_answer_reason);
    }

    public function test_vogal_cannot_close_inquiry_without_answer(): void
    {
        $vogalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $vogalUser->id,
            'seat' => FiscalCouncilSeat::VOGAL_1,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);
        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $vogalUser->id,
            'question' => 'Dúvida',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $service = app(JubanReportInquiryService::class);

        $this->expectException(FiscalCouncilReviewException::class);
        $this->expectExceptionMessage('Apenas o Relator do Conselho Fiscal ou Super Administrador pode encerrar um pedido sem resposta.');

        $service->closeWithoutAnswer($inquiry, $vogalUser, 'Motivo qualquer');
    }

    public function test_super_admin_can_close_inquiry_without_answer(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);
        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => User::factory()->create()->id,
            'question' => 'Dúvida',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $service = app(JubanReportInquiryService::class);
        $closed = $service->closeWithoutAnswer($inquiry, $superAdmin, 'Encerrado pelo SuperAdmin');

        $this->assertEquals(JubanReportInquiryStatus::ENCERRADO_SEM_RESPOSTA, $closed->status);
        $this->assertEquals($superAdmin->id, $closed->closed_without_answer_by);
    }
}
