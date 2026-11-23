<?php

namespace App\Services\Juban;

use App\Models\JubanReport;
use Barryvdh\DomPDF\Facade\Pdf;

class SignatureReceiptPdfService
{
    /**
     * Gera o conteúdo binário do PDF de Comprovante Oficial de Assinaturas.
     */
    public function generateReceiptPdf(JubanReport $report): string
    {
        $report->loadMissing(['closedBy', 'signatures.user', 'signatures.passkey']);

        $pdf = Pdf::loadView('pdf.juban.signature-receipt', [
            'report' => $report,
            'generatedAt' => now(),
        ]);

        $pdf->setPaper('a4', 'portrait');

        return (string) $pdf->output();
    }
}
