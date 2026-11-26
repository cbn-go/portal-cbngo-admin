<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\JubanReport;
use App\Models\User;
use App\Policies\JubanReportPolicy;
use App\Services\Juban\Exceptions\QuarterlyClosingException;
use App\Services\Juban\QuarterlyClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JubanReportReopenPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reopen_is_allowed_before_presidency_signs(): void
    {
        Storage::fake('local');
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $policy = app(JubanReportPolicy::class);
        $service = app(QuarterlyClosingService::class);

        // Permitido em AGUARDANDO_TESOURARIA
        $reportTesouraria = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
            'pdf_path' => 'fake.pdf',
        ]);
        Storage::disk('local')->put('fake.pdf', 'dummy');

        $this->assertTrue($policy->reopen($superAdmin, $reportTesouraria));
        $service->reopenQuarter($reportTesouraria, $superAdmin, 'Correção solicitada');
        $this->assertEquals(JubanReportStatus::REABERTO, $reportTesouraria->fresh()->status);

        // Permitido em AGUARDANDO_PRESIDENCIA
        $reportPresidencia = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 2,
            'status' => JubanReportStatus::AGUARDANDO_PRESIDENCIA,
            'pdf_path' => 'fake2.pdf',
        ]);
        Storage::disk('local')->put('fake2.pdf', 'dummy');

        $this->assertTrue($policy->reopen($superAdmin, $reportPresidencia));
        $service->reopenQuarter($reportPresidencia, $superAdmin, 'Correção pré-presidência');
        $this->assertEquals(JubanReportStatus::REABERTO, $reportPresidencia->fresh()->status);
    }

    public function test_reopen_is_forbidden_once_sent_to_or_processed_by_fiscal_council(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $policy = app(JubanReportPolicy::class);
        $service = app(QuarterlyClosingService::class);

        $forbiddenStatuses = [
            JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
            JubanReportStatus::EM_REVISAO,
            JubanReportStatus::APROVADO,
            JubanReportStatus::APROVADO_COM_RESSALVAS,
            JubanReportStatus::REPROVADO,
            JubanReportStatus::ASSINADO,
        ];

        $quarter = 1;
        foreach ($forbiddenStatuses as $status) {
            $report = JubanReport::factory()->create([
                'year' => 2025,
                'quarter' => $quarter++,
                'status' => $status,
            ]);

            $this->assertFalse($policy->reopen($superAdmin, $report), "Reopen deveria ser falso para status {$status->value}");

            $this->expectException(QuarterlyClosingException::class);
            $service->reopenQuarter($report, $superAdmin, 'Tentativa indevida');
        }
    }
}
