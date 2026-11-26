<?php

namespace Tests\Unit\Policies;

use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\JubanReport;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Policies\JubanReportPolicy;
use App\Policies\JubanTransactionPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalCouncilReportPolicyTest extends TestCase
{
    use RefreshDatabase;

    private JubanReportPolicy $reportPolicy;

    private JubanTransactionPolicy $transactionPolicy;

    private User $fiscalCouncilUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reportPolicy = new JubanReportPolicy;
        $this->transactionPolicy = new JubanTransactionPolicy;

        $this->fiscalCouncilUser = User::factory()->create([
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => true,
        ]);
    }

    public function test_fiscal_council_can_view_any_reports(): void
    {
        $this->assertTrue($this->reportPolicy->viewAny($this->fiscalCouncilUser));
    }

    public function test_fiscal_council_can_view_reports_waiting_fiscal_council_or_signed(): void
    {
        $reportWaiting = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'status' => JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
        ]);

        $reportSigned = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 2,
            'status' => JubanReportStatus::ASSINADO,
        ]);

        $this->assertTrue($this->reportPolicy->view($this->fiscalCouncilUser, $reportWaiting));
        $this->assertTrue($this->reportPolicy->view($this->fiscalCouncilUser, $reportSigned));
    }

    public function test_fiscal_council_cannot_view_reports_in_treasury_presidency_or_reopened(): void
    {
        $reportTreasury = JubanReport::factory()->create([
            'year' => 2025,
            'quarter' => 1,
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $reportPresidency = JubanReport::factory()->create([
            'year' => 2025,
            'quarter' => 2,
            'status' => JubanReportStatus::AGUARDANDO_PRESIDENCIA,
        ]);

        $reportReopened = JubanReport::factory()->create([
            'year' => 2025,
            'quarter' => 3,
            'status' => JubanReportStatus::REABERTO,
        ]);

        $this->assertFalse($this->reportPolicy->view($this->fiscalCouncilUser, $reportTreasury));
        $this->assertFalse($this->reportPolicy->view($this->fiscalCouncilUser, $reportPresidency));
        $this->assertFalse($this->reportPolicy->view($this->fiscalCouncilUser, $reportReopened));
    }

    public function test_fiscal_council_cannot_create_update_delete_or_reopen_reports(): void
    {
        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
        ]);

        $this->assertFalse($this->reportPolicy->create($this->fiscalCouncilUser));
        $this->assertFalse($this->reportPolicy->update($this->fiscalCouncilUser, $report));
        $this->assertFalse($this->reportPolicy->delete($this->fiscalCouncilUser, $report));
        $this->assertFalse($this->reportPolicy->reopen($this->fiscalCouncilUser, $report));
    }

    public function test_fiscal_council_has_no_access_to_live_transactions(): void
    {
        $transaction = JubanTransaction::factory()->create();

        $this->assertFalse($this->transactionPolicy->viewAny($this->fiscalCouncilUser));
        $this->assertFalse($this->transactionPolicy->view($this->fiscalCouncilUser, $transaction));
        $this->assertFalse($this->transactionPolicy->create($this->fiscalCouncilUser));
        $this->assertFalse($this->transactionPolicy->update($this->fiscalCouncilUser, $transaction));
        $this->assertFalse($this->transactionPolicy->delete($this->fiscalCouncilUser, $transaction));
    }
}
