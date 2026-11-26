<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\JubanReport;
use App\Models\User;
use App\Notifications\JubanReportFiscalReviewResultNotification;
use App\Services\FiscalCouncil\Exceptions\FiscalCouncilReviewException;
use App\Services\FiscalCouncil\FiscalCouncilReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class FiscalCouncilReviewServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_start_review_transitions_to_em_revisao(): void
    {
        $user = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL]);
        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
        ]);

        $service = app(FiscalCouncilReviewService::class);
        $service->startReview($report, $user);

        $this->assertEquals(JubanReportStatus::EM_REVISAO, $report->fresh()->status);
    }

    public function test_conclude_review_with_approval_notifies_juban_leadership(): void
    {
        Notification::fake();

        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL]);
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'status' => JubanReportStatus::EM_REVISAO,
        ]);

        $service = app(FiscalCouncilReviewService::class);
        $service->concludeReview($report, JubanReportStatus::APROVADO, $fiscalUser);

        $this->assertEquals(JubanReportStatus::APROVADO, $report->fresh()->status);

        Notification::assertSentTo(
            [$treasurer, $president],
            JubanReportFiscalReviewResultNotification::class,
            fn ($notification) => $notification->resultStatus === JubanReportStatus::APROVADO
        );
    }

    public function test_conclude_review_with_rejection_notifies_juban_leadership(): void
    {
        Notification::fake();

        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL]);
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'status' => JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
        ]);

        $service = app(FiscalCouncilReviewService::class);
        $service->concludeReview($report, JubanReportStatus::REPROVADO, $fiscalUser, 'Documentação incompleta');

        $this->assertEquals(JubanReportStatus::REPROVADO, $report->fresh()->status);

        Notification::assertSentTo(
            [$treasurer, $president],
            JubanReportFiscalReviewResultNotification::class,
            fn ($notification) => $notification->resultStatus === JubanReportStatus::REPROVADO
        );
    }

    public function test_cannot_conclude_with_non_review_result_status(): void
    {
        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL]);
        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::EM_REVISAO,
        ]);

        $service = app(FiscalCouncilReviewService::class);

        $this->expectException(FiscalCouncilReviewException::class);
        $service->concludeReview($report, JubanReportStatus::REABERTO, $fiscalUser);
    }
}
