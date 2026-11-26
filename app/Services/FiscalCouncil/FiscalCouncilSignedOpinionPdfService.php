<?php

namespace App\Services\FiscalCouncil;

use App\Models\JubanReportOpinion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class FiscalCouncilSignedOpinionPdfService
{
    public function __construct(
        protected FiscalCouncilOpinionTextService $textService
    ) {}

    /**
     * Gera o PDF completo assinado (Via Assinada) com página de manifesto de assinaturas
     * e armazena no disco local privado, preservando o parecer emitido original intacto.
     *
     * @return array{signed_pdf_path: string, signed_pdf_sha256: string}
     */
    public function generateSignedCopy(JubanReportOpinion $opinion): array
    {
        $opinion->loadMissing([
            'report.inquiries',
            'members.member.user',
            'signatures.user',
            'signatures.passkey',
        ]);

        $mainText = $this->textService->buildMainText($opinion);
        $issuedOnText = $this->textService->formatDateExtenso($opinion->issued_on);

        $pdf = Pdf::loadView('pdf.fiscal-council.opinion-signed', [
            'opinion' => $opinion,
            'mainText' => $mainText,
            'issuedOnText' => $issuedOnText,
        ]);

        $pdf->setPaper('a4', 'portrait');

        $pdfBytes = (string) $pdf->output();
        $signedPdfSha256 = hash('sha256', $pdfBytes);

        $year = $opinion->report->year;
        $quarter = $opinion->report->quarter;
        $version = $opinion->report->version;

        $versionSuffix = $version > 1 ? "-v{$version}" : '';
        $timestamp = now()->format('YmdHis');
        $filename = "{$year}-q{$quarter}{$versionSuffix}-parecer-fiscal-assinado-{$timestamp}.pdf";
        $signedPdfPath = "juban-opinions/{$filename}";

        Storage::disk('local')->put($signedPdfPath, $pdfBytes);

        return [
            'signed_pdf_path' => $signedPdfPath,
            'signed_pdf_sha256' => $signedPdfSha256,
        ];
    }
}
