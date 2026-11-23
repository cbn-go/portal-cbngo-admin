<?php

namespace Tests\Unit\Services\Juban;

use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Models\JubanReport;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use App\Services\Juban\SignatureReceiptPdfService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignatureReceiptPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private SignatureReceiptPdfService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SignatureReceiptPdfService;
    }

    public function test_generates_receipt_pdf_with_all_signatures_and_hashes(): void
    {
        $treasurer = User::factory()->create(['name' => 'Carlos Tesoureiro']);
        $president = User::factory()->create(['name' => 'Ana Presidente']);

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 3,
            'status' => JubanReportStatus::ASSINADO,
            'pdf_sha256' => hash('sha256', 'Relatório Oficial Q3 2026'),
        ]);

        $treasurerPasskey = Passkey::factory()->create([
            'user_id' => $treasurer->id,
            'name' => 'MacBook TouchID',
        ]);

        $presidentPasskey = Passkey::factory()->create([
            'user_id' => $president->id,
            'name' => 'iPhone FaceID',
        ]);

        JubanReportSignature::factory()->create([
            'report_id' => $report->id,
            'user_id' => $treasurer->id,
            'role' => JubanSigningRole::TESOURARIA,
            'passkey_id' => $treasurerPasskey->id,
            'pdf_sha256' => $report->pdf_sha256,
            'signed_at' => now()->subDay(),
        ]);

        JubanReportSignature::factory()->create([
            'report_id' => $report->id,
            'user_id' => $president->id,
            'role' => JubanSigningRole::PRESIDENCIA,
            'passkey_id' => $presidentPasskey->id,
            'pdf_sha256' => $report->pdf_sha256,
            'signed_at' => now(),
        ]);

        $pdfOutput = $this->service->generateReceiptPdf($report->fresh());

        $this->assertNotEmpty($pdfOutput);
        $this->assertStringStartsWith('%PDF-', $pdfOutput);
    }
}
