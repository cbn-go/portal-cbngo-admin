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
use App\Services\FiscalCouncil\FiscalCouncilReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JubanReportInquiryReviewBlockingTest extends TestCase
{
    use RefreshDatabase;

    public function test_conclude_review_is_blocked_when_report_has_open_inquiries(): void
    {
        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);

        // Cria pedido de esclarecimento em aberto
        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $fiscalUser->id,
            'question' => 'Aguardando detalhamento de nota',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $service = app(FiscalCouncilReviewService::class);

        $this->expectException(FiscalCouncilReviewException::class);
        $this->expectExceptionMessage('Não é possível concluir a revisão com pedidos de esclarecimento em aberto.');

        $service->concludeReview($report, JubanReportStatus::APROVADO, $fiscalUser);
    }

    public function test_conclude_review_is_allowed_when_all_inquiries_are_answered_or_closed_without_answer(): void
    {
        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);

        // 1 pedido respondido
        JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $fiscalUser->id,
            'question' => 'Pergunta 1',
            'asked_at' => now()->subDay(),
            'status' => JubanReportInquiryStatus::RESPONDIDO,
            'answer' => 'Resposta 1',
        ]);

        // 1 pedido encerrado sem resposta pelo relator
        JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $fiscalUser->id,
            'question' => 'Pergunta 2',
            'asked_at' => now()->subDay(),
            'status' => JubanReportInquiryStatus::ENCERRADO_SEM_RESPOSTA,
            'closed_without_answer_by' => $fiscalUser->id,
            'closed_without_answer_at' => now(),
            'closed_without_answer_reason' => 'Encerrado',
        ]);

        $service = app(FiscalCouncilReviewService::class);
        $service->concludeReview($report, JubanReportStatus::APROVADO, $fiscalUser);

        $this->assertEquals(JubanReportStatus::APROVADO, $report->fresh()->status);
    }
}
