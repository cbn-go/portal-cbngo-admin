<?php

namespace Tests\Unit\Models;

use App\Enums\JubanReportStatus;
use App\Models\JubanReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JubanReportVersionLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_date_is_unlocked_when_no_report_exists(): void
    {
        $this->assertFalse(JubanReport::isDateLocked('2026-02-15'));
    }

    public function test_date_is_locked_when_active_report_version_exists(): void
    {
        JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $this->assertTrue(JubanReport::isDateLocked('2026-02-15'));
    }

    public function test_date_is_locked_when_version_is_reprovado(): void
    {
        JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::REPROVADO,
        ]);

        $this->assertTrue(JubanReport::isDateLocked('2026-02-15'));
    }

    public function test_date_is_unlocked_when_latest_version_is_reaberto(): void
    {
        // Versão 1: reprovada (travada)
        JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::REPROVADO,
        ]);

        // Versão 2: nova versão criada após reprovação (reaberta para ajustes)
        JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 2,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::REABERTO,
        ]);

        $this->assertFalse(JubanReport::isDateLocked('2026-02-15'));
    }

    public function test_date_is_locked_again_when_latest_version_is_closed(): void
    {
        JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::REPROVADO,
        ]);

        JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 2,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $this->assertTrue(JubanReport::isDateLocked('2026-02-15'));
    }
}
