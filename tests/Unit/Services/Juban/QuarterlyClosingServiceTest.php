<?php

namespace Tests\Unit\Services\Juban;

use App\Enums\JubanCategoryKind;
use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\JubanCategory;
use App\Models\JubanReport;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Services\Juban\CashSummary;
use App\Services\Juban\Exceptions\QuarterlyClosingException;
use App\Services\Juban\QuarterlyClosingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuarterlyClosingServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuarterlyClosingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->service = app(QuarterlyClosingService::class);
    }

    public function test_cannot_close_ongoing_quarter(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);

        // Simula que hoje é 15/02/2026 (dentro do Q1 de 2026, que termina em 31/03/2026)
        Carbon::setTestNow('2026-02-15 12:00:00');

        $this->expectException(QuarterlyClosingException::class);
        $this->expectExceptionMessage('Não é possível fechar um trimestre que ainda está em andamento.');

        $this->service->closeQuarter(2026, 1, $treasurer);
    }

    public function test_cannot_close_duplicate_quarter(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);

        // Simula que hoje é 05/04/2025 (Q1 de 2025 já terminou)
        Carbon::setTestNow('2025-04-05 10:00:00');

        // Cria relatório prévio para 2025 Q1
        JubanReport::factory()->create([
            'year' => 2025,
            'quarter' => 1,
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $this->expectException(QuarterlyClosingException::class);
        $this->expectExceptionMessage('O trimestre Q1/2025 já foi fechado.');

        $this->service->closeQuarter(2025, 1, $treasurer);
    }

    public function test_snapshot_matches_cash_summary_for_period(): void
    {
        $treasurer = User::factory()->create([
            'role' => UserRole::JUBAN_TREASURER,
            'name' => 'Fábio Tesoureiro',
        ]);

        // Simula data após o fim do Q1 de 2025
        Carbon::setTestNow('2025-04-02 09:00:00');

        $categoryReceita = JubanCategory::factory()->create(['kind' => JubanCategoryKind::RECEITA, 'name' => 'Inscrições']);
        $categoryDespesa = JubanCategory::factory()->create(['kind' => JubanCategoryKind::DESPESA, 'name' => 'Alimentação']);

        // Transações dentro do Q1 (01/01/2025 a 31/03/2025)
        JubanTransaction::factory()->receita()->create([
            'category_id' => $categoryReceita->id,
            'amount' => 5000.00,
            'occurred_on' => '2025-01-15',
            'description' => 'Inscrição Retiro',
        ]);

        JubanTransaction::factory()->despesa()->create([
            'category_id' => $categoryDespesa->id,
            'amount' => 1200.00,
            'occurred_on' => '2025-02-20',
            'description' => 'Alimentação Retiro',
        ]);

        // Transação fora do período (não deve entrar)
        JubanTransaction::factory()->receita()->create([
            'category_id' => $categoryReceita->id,
            'amount' => 9999.00,
            'occurred_on' => '2025-04-01',
        ]);

        $expectedCashSummary = app(CashSummary::class)->forPeriod(
            Carbon::parse('2025-01-01'),
            Carbon::parse('2025-03-31')
        );

        $report = $this->service->closeQuarter(2025, 1, $treasurer);

        $this->assertInstanceOf(JubanReport::class, $report);
        $this->assertSame(2025, $report->year);
        $this->assertSame(1, $report->quarter);
        $this->assertSame('2025-01-01', $report->period_from->toDateString());
        $this->assertSame('2025-03-31', $report->period_until->toDateString());
        $this->assertSame(JubanReportStatus::AGUARDANDO_TESOURARIA, $report->status);
        $this->assertSame($treasurer->id, $report->closed_by);

        // Validação estrita do snapshot
        $snapshot = $report->snapshot;
        $this->assertEquals($expectedCashSummary->receitas, $snapshot['summary']['receitas']);
        $this->assertEquals($expectedCashSummary->despesas, $snapshot['summary']['despesas']);
        $this->assertEquals($expectedCashSummary->saldo, $snapshot['summary']['saldo']);
        $this->assertCount(2, $snapshot['transactions']);
        $this->assertSame('Inscrição Retiro', $snapshot['transactions'][0]['description']);
    }

    public function test_generates_pdf_and_persists_sha256_hash(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);

        Carbon::setTestNow('2025-07-05 10:00:00');

        $report = $this->service->closeQuarter(2025, 2, $treasurer);

        $this->assertNotNull($report->pdf_path);
        $this->assertNotNull($report->pdf_sha256);
        $this->assertSame(64, strlen($report->pdf_sha256));

        // O arquivo deve existir no disco
        Storage::disk('local')->assertExists($report->pdf_path);

        $pdfBytes = Storage::disk('local')->get($report->pdf_path);
        $this->assertSame(hash('sha256', $pdfBytes), $report->pdf_sha256);
    }

    public function test_super_admin_can_reopen_uncompleted_report_and_file_is_removed(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);

        Carbon::setTestNow('2025-04-02 09:00:00');

        $report = $this->service->closeQuarter(2025, 1, $treasurer);
        $filePath = $report->pdf_path;

        Storage::disk('local')->assertExists($filePath);

        $this->service->reopenQuarter($report, $superAdmin, 'Correção de nota fiscal esquecida');

        $report->refresh();

        $this->assertSame(JubanReportStatus::REABERTO, $report->status);
        $this->assertSame($superAdmin->id, $report->reopened_by);
        $this->assertNotNull($report->reopened_at);
        $this->assertSame('Correção de nota fiscal esquecida', $report->reopen_reason);
        $this->assertNull($report->pdf_path);
        $this->assertNull($report->pdf_sha256);

        // O arquivo PDF deve ter sido removido do storage
        Storage::disk('local')->assertMissing($filePath);
    }

    public function test_cannot_reopen_fully_signed_report(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);

        $report = JubanReport::factory()->assinado()->create([
            'year' => 2024,
            'quarter' => 4,
        ]);

        $this->expectException(QuarterlyClosingException::class);
        $this->expectExceptionMessage('Relatórios já assinados por ambas as partes não podem ser reabertos.');

        $this->service->reopenQuarter($report, $superAdmin, 'Tentativa de reabrir relatório assinado');
    }

    public function test_non_super_admin_cannot_reopen_report(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);

        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
        ]);

        $this->expectException(QuarterlyClosingException::class);
        $this->expectExceptionMessage('Apenas o Super Administrador pode reabrir um trimestre fechado.');

        $this->service->reopenQuarter($report, $treasurer, 'Tentativa sem permissão');
    }
}
