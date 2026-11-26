<?php

namespace Tests\Feature\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Models\JubanReport;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use App\Policies\JubanReportPolicy;
use App\Services\Juban\Exceptions\QuarterlyClosingException;
use App\Services\Juban\QuarterlyClosingService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class JubanReportNewVersionAfterRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_creates_new_version_after_rejection(): void
    {
        Storage::fake('local');
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);

        // Relatório v1 reprovado com PDF e assinaturas
        $v1PdfPath = 'juban-reports/2026-q1-v1-relatorio.pdf';
        Storage::disk('local')->put($v1PdfPath, 'PDF content v1');

        $v1Report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'status' => JubanReportStatus::REPROVADO,
            'pdf_path' => $v1PdfPath,
            'pdf_sha256' => hash('sha256', 'PDF content v1'),
        ]);

        $passkey = Passkey::factory()->create(['user_id' => $treasurer->id]);
        $signature = JubanReportSignature::create([
            'report_id' => $v1Report->id,
            'user_id' => $treasurer->id,
            'role' => JubanSigningRole::TESOURARIA,
            'passkey_id' => $passkey->id,
            'nonce' => 'nonce123',
            'challenge' => 'challenge123',
            'client_data_json' => '{}',
            'authenticator_data' => 'auth',
            'signature' => 'sig',
            'pdf_sha256' => $v1Report->pdf_sha256,
            'signed_at' => now(),
        ]);

        $policy = app(JubanReportPolicy::class);
        $this->assertTrue($policy->createNewVersion($superAdmin, $v1Report));

        $service = app(QuarterlyClosingService::class);
        $v2Report = $service->createNewVersionAfterRejection($v1Report, $superAdmin, 'Ajustar comprovantes faltantes da rubrica X');

        // Validações da nova versão
        $this->assertEquals(2026, $v2Report->year);
        $this->assertEquals(1, $v2Report->quarter);
        $this->assertEquals(2, $v2Report->version);
        $this->assertEquals(JubanReportStatus::REABERTO, $v2Report->status);
        $this->assertNull($v2Report->pdf_path);
        $this->assertEquals($superAdmin->id, $v2Report->reopened_by);
        $this->assertEquals('Ajustar comprovantes faltantes da rubrica X', $v2Report->reopen_reason);

        // Validação da versão anterior: permanece intocada no banco e em disco
        $v1Fresh = $v1Report->fresh();
        $this->assertEquals(JubanReportStatus::REPROVADO, $v1Fresh->status);
        $this->assertEquals(1, $v1Fresh->version);
        $this->assertEquals($v1PdfPath, $v1Fresh->pdf_path);
        Storage::disk('local')->assertExists($v1PdfPath);
        $this->assertCount(1, $v1Fresh->signatures);

        // Não permite criar outra versão 2 se v2 já existe
        $this->expectException(QuarterlyClosingException::class);
        $service->createNewVersionAfterRejection($v1Report, $superAdmin, 'Tentativa duplicada');
    }

    public function test_non_super_admin_cannot_create_new_version(): void
    {
        $treasurer = User::factory()->create(['role' => UserRole::JUBAN_TREASURER]);
        $v1Report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'status' => JubanReportStatus::REPROVADO,
        ]);

        $policy = app(JubanReportPolicy::class);
        $this->assertFalse($policy->createNewVersion($treasurer, $v1Report));

        $service = app(QuarterlyClosingService::class);
        $this->expectException(QuarterlyClosingException::class);
        $service->createNewVersionAfterRejection($v1Report, $treasurer, 'Sem permissão');
    }

    public function test_cannot_create_new_version_if_report_is_not_reprovado(): void
    {
        $superAdmin = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $reportAprovado = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'version' => 1,
            'status' => JubanReportStatus::APROVADO,
        ]);

        $service = app(QuarterlyClosingService::class);
        $this->expectException(QuarterlyClosingException::class);
        $service->createNewVersionAfterRejection($reportAprovado, $superAdmin, 'Não pode');
    }
}
