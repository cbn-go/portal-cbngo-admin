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
use App\Policies\JubanReportInquiryPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JubanReportInquirySecurityAndImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_inquiries_are_immutable_and_cannot_be_updated_or_deleted(): void
    {
        $policy = app(JubanReportInquiryPolicy::class);
        $user = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);

        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $user->id,
            'question' => 'Pergunta original',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $this->assertFalse($policy->update($user, $inquiry));
        $this->assertFalse($policy->delete($user, $inquiry));
    }

    public function test_attachment_download_is_allowed_for_authorized_roles_and_denied_for_unrelated_roles(): void
    {
        Storage::fake('local');

        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER, 'is_active' => true]);
        $president = User::factory()->create(['role' => UserRole::JUBAN_PRESIDENT, 'is_active' => true]);
        $unrelatedUser = User::factory()->create(['role' => UserRole::CONVENTION_EDITOR, 'is_active' => true]);

        $report = JubanReport::factory()->create(['status' => JubanReportStatus::EM_REVISAO]);

        $filePath = 'juban-inquiries/2026-q1/recibo-teste.pdf';
        Storage::disk('local')->put($filePath, 'Fake PDF Content');

        $inquiry = JubanReportInquiry::create([
            'report_id' => $report->id,
            'asked_by' => $fiscalUser->id,
            'question' => 'Comprovante',
            'asked_at' => now(),
            'status' => JubanReportInquiryStatus::RESPONDIDO,
            'answer' => 'Segue',
            'answered_by' => $treasurer->id,
            'answered_at' => now(),
            'attachment_path' => $filePath,
        ]);

        $policy = app(JubanReportInquiryPolicy::class);

        $this->assertTrue($policy->downloadAttachment($fiscalUser, $inquiry));
        $this->assertTrue($policy->downloadAttachment($treasurer, $inquiry));
        $this->assertTrue($policy->downloadAttachment($president, $inquiry));
        $this->assertFalse($policy->downloadAttachment($unrelatedUser, $inquiry));

        // Teste de download via endpoint
        $responseAuthorized = $this->actingAs($fiscalUser)->get(route('juban.inquiries.download-attachment', $inquiry));
        $responseAuthorized->assertOk();

        $responseDenied = $this->actingAs($unrelatedUser)->get(route('juban.inquiries.download-attachment', $inquiry));
        $responseDenied->assertForbidden();
    }
}
