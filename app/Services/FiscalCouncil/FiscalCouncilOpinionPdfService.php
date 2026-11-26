<?php

namespace App\Services\FiscalCouncil;

use App\Models\JubanReportOpinion;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class FiscalCouncilOpinionPdfService
{
    public function __construct(
        protected FiscalCouncilOpinionTextService $textService
    ) {}

    /**
     * Renderiza o conteúdo binário do PDF do parecer fiscal.
     */
    public function renderPdf(JubanReportOpinion $opinion): string
    {
        $opinion->loadMissing(['report.inquiries', 'members.member.user']);

        $mainText = $this->textService->buildMainText($opinion);
        $issuedOnText = $this->textService->formatDateExtenso($opinion->issued_on);

        $pdf = Pdf::loadView('pdf.fiscal-council.opinion', [
            'opinion' => $opinion,
            'mainText' => $mainText,
            'issuedOnText' => $issuedOnText,
        ]);

        $pdf->setPaper('a4', 'portrait');

        return (string) $pdf->output();
    }

    /**
     * Gera o PDF, armazena no disco local privado e calcula o SHA-256.
     *
     * @return array{pdf_path: string, pdf_sha256: string}
     */
    public function generateAndStore(JubanReportOpinion $opinion): array
    {
        $pdfBytes = $this->renderPdf($opinion);
        $pdfSha256 = hash('sha256', $pdfBytes);

        $year = $opinion->report->year;
        $quarter = $opinion->report->quarter;
        $version = $opinion->report->version;

        $versionSuffix = $version > 1 ? "-v{$version}" : '';
        $timestamp = now()->format('YmdHis');
        $filename = "{$year}-q{$quarter}{$versionSuffix}-parecer-fiscal-{$timestamp}.pdf";
        $pdfPath = "juban-opinions/{$filename}";

        Storage::disk('local')->put($pdfPath, $pdfBytes);

        return [
            'pdf_path' => $pdfPath,
            'pdf_sha256' => $pdfSha256,
        ];
    }
}
