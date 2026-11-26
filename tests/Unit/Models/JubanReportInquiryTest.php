<?php

namespace Tests\Unit\Models;

use App\Enums\JubanReportInquiryStatus;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\JubanTransaction;
use App\Models\User;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JubanReportInquiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_enum_implements_filament_contracts(): void
    {
        $this->assertInstanceOf(HasLabel::class, JubanReportInquiryStatus::ABERTO);
        $this->assertInstanceOf(HasColor::class, JubanReportInquiryStatus::ABERTO);
        $this->assertInstanceOf(HasIcon::class, JubanReportInquiryStatus::ABERTO);
    }

    public function test_inquiry_creation_and_relations(): void
    {
        $asker = User::factory()->create();
        $responder = User::factory()->create();
        $closer = User::factory()->create();
        $report = JubanReport::factory()->create();
        $transaction = JubanTransaction::factory()->create();

        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'transaction_id' => $transaction->id,
            'asked_by' => $asker->id,
            'question' => 'Favor anexar a nota fiscal referente a este serviço.',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
            'answered_by' => $responder->id,
            'answer' => 'Nota fiscal anexada conforme solicitado.',
            'answered_at' => now(),
            'attachment_path' => 'juban-inquiries/2026-q1/nf-123.pdf',
            'closed_without_answer_by' => $closer->id,
            'closed_without_answer_at' => now(),
            'closed_without_answer_reason' => 'Esclarecido verbalmente em reunião.',
        ]);

        $this->assertTrue($inquiry->report->is($report));
        $this->assertTrue($inquiry->transaction->is($transaction));
        $this->assertTrue($inquiry->askedBy->is($asker));
        $this->assertTrue($inquiry->answeredBy->is($responder));
        $this->assertTrue($inquiry->closedWithoutAnswerBy->is($closer));

        $this->assertInstanceOf(JubanReportInquiryStatus::class, $inquiry->status);
    }

    public function test_status_helper_methods(): void
    {
        $inquiry = new JubanReportInquiry(['status' => JubanReportInquiryStatus::ABERTO]);
        $this->assertTrue($inquiry->isOpen());
        $this->assertFalse($inquiry->isAnswered());
        $this->assertFalse($inquiry->isClosedWithoutAnswer());

        $inquiry->status = JubanReportInquiryStatus::RESPONDIDO;
        $this->assertFalse($inquiry->isOpen());
        $this->assertTrue($inquiry->isAnswered());
        $this->assertFalse($inquiry->isClosedWithoutAnswer());

        $inquiry->status = JubanReportInquiryStatus::ENCERRADO_SEM_RESPOSTA;
        $this->assertFalse($inquiry->isOpen());
        $this->assertFalse($inquiry->isAnswered());
        $this->assertTrue($inquiry->isClosedWithoutAnswer());
    }

    public function test_report_inquiries_relation_and_has_open_inquiries(): void
    {
        $report = JubanReport::factory()->create();
        $asker = User::factory()->create();

        $this->assertFalse($report->hasOpenInquiries());

        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $asker->id,
            'question' => 'Dúvida sobre recibo',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $this->assertTrue($report->fresh()->hasOpenInquiries());
        $this->assertCount(1, $report->fresh()->openInquiries);

        $inquiry->update(['status' => JubanReportInquiryStatus::RESPONDIDO]);
        $this->assertFalse($report->fresh()->hasOpenInquiries());
        $this->assertCount(0, $report->fresh()->openInquiries);
        $this->assertCount(1, $report->fresh()->inquiries);
    }
}
