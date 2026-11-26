<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Notifications\JubanReportInquiryAskedNotification;
use App\Services\FiscalCouncil\Exceptions\FiscalCouncilReviewException;
use App\Services\FiscalCouncil\JubanReportInquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class JubanReportInquiryCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_fiscal_council_member_can_open_inquiry_and_report_transitions_to_em_revisao(): void
    {
        Notification::fake();

        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::VOGAL_1,
            'term_starts_on' => now()->subMonth(),
            'term_ends_on' => now()->addYears(2),
            'active' => true,
        ]);

        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
        ]);

        $service = app(JubanReportInquiryService::class);
        $inquiry = $service->askInquiry(
            $report,
            $fiscalUser,
            'Favor detalhar as despesas de transporte da conferência estadual.'
        );

        $this->assertNotNull($inquiry->id);
        $this->assertEquals($report->id, $inquiry->report_id);
        $this->assertEquals($fiscalUser->id, $inquiry->asked_by);
        $this->assertEquals('Favor detalhar as despesas de transporte da conferência estadual.', $inquiry->question);
        $this->assertTrue($inquiry->isOpen());

        // Relatório avança automaticamente para em_revisao
        $this->assertEquals(JubanReportStatus::EM_REVISAO, $report->fresh()->status);

        // Notificação enviada à tesouraria
        Notification::assertSentTo($treasurer, JubanReportInquiryAskedNotification::class, function ($n) use ($inquiry) {
            return $n->inquiry->id === $inquiry->id;
        });
    }

    public function test_can_link_inquiry_to_specific_transaction_in_period(): void
    {
        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create([
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::EM_REVISAO,
        ]);

        $transaction = JubanTransaction::factory()->create([
            'occurred_on' => '2026-02-10',
            'amount' => 500.00,
        ]);

        $service = app(JubanReportInquiryService::class);
        $inquiry = $service->askInquiry(
            $report,
            $fiscalUser,
            'Qual o beneficiário deste pagamento de R$ 500,00?',
            $transaction->id
        );

        $this->assertEquals($transaction->id, $inquiry->transaction_id);
    }

    public function test_cannot_link_transaction_outside_report_period(): void
    {
        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create([
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::EM_REVISAO,
        ]);

        // Transação de outro trimestre
        $transaction = JubanTransaction::factory()->create([
            'occurred_on' => '2026-05-10',
        ]);

        $service = app(JubanReportInquiryService::class);

        $this->expectException(FiscalCouncilReviewException::class);
        $this->expectExceptionMessage('O lançamento selecionado não pertence ao período do relatório.');

        $service->askInquiry(
            $report,
            $fiscalUser,
            'Pergunta inválida',
            $transaction->id
        );
    }

    public function test_unauthorized_user_cannot_open_inquiry(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);
        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
        ]);

        $service = app(JubanReportInquiryService::class);

        $this->expectException(FiscalCouncilReviewException::class);
        $this->expectExceptionMessage('Apenas membros ativos do Conselho Fiscal podem abrir pedidos de esclarecimento.');

        $service->askInquiry($report, $treasurer, 'Tentativa indevida');
    }
}
