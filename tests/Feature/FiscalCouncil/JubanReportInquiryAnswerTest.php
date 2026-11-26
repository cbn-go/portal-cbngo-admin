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
use App\Notifications\JubanReportInquiryAnsweredNotification;
use App\Services\FiscalCouncil\Exceptions\FiscalCouncilReviewException;
use App\Services\FiscalCouncil\JubanReportInquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JubanReportInquiryAnswerTest extends TestCase
{
    use RefreshDatabase;

    public function test_treasurer_can_answer_inquiry_with_attachment(): void
    {
        Notification::fake();
        Storage::fake('local');

        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'status' => JubanReportStatus::EM_REVISAO,
        ]);

        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $fiscalUser->id,
            'question' => 'Favor anexar comprovante do PIX de R$ 1.200,00.',
            'asked_at' => now()->subDay(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $fakePdf = UploadedFile::fake()->create('comprovante-pix.pdf', 500, 'application/pdf');

        $service = app(JubanReportInquiryService::class);
        $answered = $service->answerInquiry(
            $inquiry,
            $treasurer,
            'Segue anexo o comprovante da transação bancária.',
            $fakePdf
        );

        $this->assertEquals(JubanReportInquiryStatus::RESPONDIDO, $answered->status);
        $this->assertEquals($treasurer->id, $answered->answered_by);
        $this->assertNotNull($answered->answered_at);
        $this->assertEquals('Segue anexo o comprovante da transação bancária.', $answered->answer);
        $this->assertNotNull($answered->attachment_path);

        Storage::disk('local')->assertExists($answered->attachment_path);

        Notification::assertSentTo($fiscalUser, JubanReportInquiryAnsweredNotification::class, function ($n) use ($answered) {
            return $n->inquiry->id === $answered->id;
        });
    }

    public function test_juban_president_cannot_answer_inquiry(): void
    {
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);
        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => User::factory()->create()->id,
            'question' => 'Dúvida',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $service = app(JubanReportInquiryService::class);

        $this->expectException(FiscalCouncilReviewException::class);
        $this->expectExceptionMessage('Apenas a Tesouraria pode responder a pedidos de esclarecimento.');

        $service->answerInquiry($inquiry, $president, 'Resposta não autorizada');
    }

    public function test_fiscal_council_member_cannot_answer_inquiry(): void
    {
        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);
        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => User::factory()->create()->id,
            'question' => 'Dúvida',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $service = app(JubanReportInquiryService::class);

        $this->expectException(FiscalCouncilReviewException::class);
        $this->expectExceptionMessage('Apenas a Tesouraria pode responder a pedidos de esclarecimento.');

        $service->answerInquiry($inquiry, $fiscalUser, 'Resposta do próprio conselho');
    }

    public function test_cannot_answer_already_answered_or_closed_inquiry(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);

        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => User::factory()->create()->id,
            'question' => 'Dúvida',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::RESPONDIDO,
            'answer' => 'Já respondido',
        ]);

        $service = app(JubanReportInquiryService::class);

        $this->expectException(FiscalCouncilReviewException::class);
        $this->expectExceptionMessage('Este pedido de esclarecimento não está aberto para resposta.');

        $service->answerInquiry($inquiry, $treasurer, 'Segunda resposta');
    }
}
