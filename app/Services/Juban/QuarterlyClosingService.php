<?php

namespace App\Services\Juban;

use App\Enums\JubanReportStatus;
use App\Models\JubanReport;
use App\Models\JubanTransaction;
use App\Models\User;
use App\Services\Juban\Exceptions\QuarterlyClosingException;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class QuarterlyClosingService
{
    /**
     * @return array{0: string, 1: string} [period_from, period_until]
     */
    public static function quarterDates(int $year, int $quarter): array
    {
        return match ($quarter) {
            1 => ["{$year}-01-01", "{$year}-03-31"],
            2 => ["{$year}-04-01", "{$year}-06-30"],
            3 => ["{$year}-07-01", "{$year}-09-30"],
            4 => ["{$year}-10-01", "{$year}-12-31"],
            default => throw new QuarterlyClosingException("Trimestre inválido: {$quarter}. Deve ser entre 1 e 4."),
        };
    }

    public function closeQuarter(int $year, int $quarter, User $closedBy): JubanReport
    {
        [$periodFrom, $periodUntil] = self::quarterDates($year, $quarter);

        // 1. Fechamento só pode ocorrer após o término do período
        if (now()->toDateString() <= $periodUntil) {
            throw new QuarterlyClosingException('Não é possível fechar um trimestre que ainda está em andamento.');
        }

        // 2. Não fecha trimestre duplicado
        $latest = JubanReport::where('year', $year)
            ->where('quarter', $quarter)
            ->orderByDesc('version')
            ->first();

        if ($latest && $latest->status !== JubanReportStatus::REABERTO) {
            throw new QuarterlyClosingException("O trimestre Q{$quarter}/{$year} já foi fechado.");
        }

        // 3. Snapshot fiel gerado via CashSummary
        $from = Carbon::parse($periodFrom);
        $until = Carbon::parse($periodUntil);
        $cashSummary = app(CashSummary::class)->forPeriod($from, $until);

        $transactions = JubanTransaction::query()
            ->whereDate('occurred_on', '>=', $periodFrom)
            ->whereDate('occurred_on', '<=', $periodUntil)
            ->with(['category', 'creator'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->get()
            ->map(fn (JubanTransaction $t) => [
                'id' => $t->id,
                'occurred_on' => $t->occurred_on->toDateString(),
                'type' => $t->type->value,
                'amount' => (float) $t->amount,
                'category_name' => $t->category->name,
                'description' => $t->description,
                'payment_method' => $t->payment_method?->value,
                'document_ref' => $t->document_ref,
                'notes' => $t->notes,
                'created_by' => $t->creator?->name,
            ])
            ->all();

        $categories = collect($cashSummary->categories)
            ->map(fn (CashSummaryCategory $c) => [
                'category_id' => $c->categoryId,
                'name' => $c->name,
                'type' => $c->type->value,
                'count' => $c->count,
                'total' => (float) $c->total,
                'percent' => (float) $c->percentOfType,
            ])
            ->values()
            ->all();

        $monthlyDespesas = collect($cashSummary->monthlyDespesas)
            ->map(fn (array $m) => [
                'label' => $m['month']->locale('pt_BR')->translatedFormat('M/Y'),
                'month' => $m['month']->format('Y-m'),
                'amount' => (float) $m['total'],
            ])
            ->all();

        $snapshot = [
            'metadata' => [
                'generated_at' => now()->toIso8601String(),
                'closed_by_id' => $closedBy->id,
                'closed_by_name' => $closedBy->name,
                'closed_by_role' => $closedBy->role->getLabel(),
            ],
            'period' => [
                'year' => $year,
                'quarter' => $quarter,
                'from' => $periodFrom,
                'until' => $periodUntil,
            ],
            'summary' => [
                'receitas' => (float) $cashSummary->receitas,
                'despesas' => (float) $cashSummary->despesas,
                'saldo' => (float) $cashSummary->saldo,
                'total_transactions' => count($transactions),
            ],
            'categories' => $categories,
            'monthly_despesas' => $monthlyDespesas,
            'transactions' => $transactions,
        ];

        // 4. Cria ou atualiza o registro do relatório
        if ($latest && $latest->status === JubanReportStatus::REABERTO) {
            $latest->update([
                'period_from' => $periodFrom,
                'period_until' => $periodUntil,
                'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
                'snapshot' => $snapshot,
                'closed_by' => $closedBy->id,
                'closed_at' => now(),
                'reopened_by' => null,
                'reopened_at' => null,
                'reopen_reason' => null,
            ]);
            $report = $latest;
        } else {
            $report = JubanReport::create([
                'year' => $year,
                'quarter' => $quarter,
                'version' => 1,
                'period_from' => $periodFrom,
                'period_until' => $periodUntil,
                'status' => JubanReportStatus::AGUARDANDO_TESOURARIA,
                'snapshot' => $snapshot,
                'closed_by' => $closedBy->id,
                'closed_at' => now(),
                'reopened_by' => null,
                'reopened_at' => null,
                'reopen_reason' => null,
            ]);
        }

        // 5. Gera o PDF do relatório
        $pdf = Pdf::loadView('pdf.juban.quarterly-report', ['report' => $report])
            ->setPaper('a4', 'portrait');

        $pdfBytes = $pdf->output();
        $pdfPath = $report->version > 1
            ? "juban-reports/{$year}-q{$quarter}-v{$report->version}-relatorio.pdf"
            : "juban-reports/{$year}-q{$quarter}-relatorio.pdf";

        Storage::disk('local')->put($pdfPath, $pdfBytes);
        $pdfSha256 = hash('sha256', $pdfBytes);

        $report->update([
            'pdf_path' => $pdfPath,
            'pdf_sha256' => $pdfSha256,
        ]);

        return $report;
    }

    public function reopenQuarter(JubanReport $report, User $reopenedBy, ?string $reason = null): void
    {
        if (! $reopenedBy->role->isSuperAdmin()) {
            throw new QuarterlyClosingException('Apenas o Super Administrador pode reabrir um trimestre fechado.');
        }

        if (! in_array($report->status, [
            JubanReportStatus::AGUARDANDO_TESOURARIA,
            JubanReportStatus::AGUARDANDO_PRESIDENCIA,
        ], true)) {
            throw new QuarterlyClosingException('A reabertura só é permitida antes da assinatura da presidência. Relatórios já assinados por ambas as partes não podem ser reabertos.');
        }

        if ($report->pdf_path && Storage::disk('local')->exists($report->pdf_path)) {
            Storage::disk('local')->delete($report->pdf_path);
        }

        $report->update([
            'status' => JubanReportStatus::REABERTO,
            'pdf_path' => null,
            'pdf_sha256' => null,
            'reopened_by' => $reopenedBy->id,
            'reopened_at' => now(),
            'reopen_reason' => $reason,
        ]);
    }

    /**
     * Abre uma nova versão do trimestre após reprovação pelo Conselho Fiscal.
     */
    public function createNewVersionAfterRejection(JubanReport $rejectedReport, User $creator, string $reason): JubanReport
    {
        if (! $creator->role->isSuperAdmin()) {
            throw new QuarterlyClosingException('Apenas o Super Administrador pode abrir uma nova versão após reprovação.');
        }

        if ($rejectedReport->status !== JubanReportStatus::REPROVADO) {
            throw new QuarterlyClosingException('Uma nova versão só pode ser criada após a reprovação do relatório.');
        }

        $hasHigherVersion = JubanReport::where('year', $rejectedReport->year)
            ->where('quarter', $rejectedReport->quarter)
            ->where('version', '>', $rejectedReport->version)
            ->exists();

        if ($hasHigherVersion) {
            throw new QuarterlyClosingException('Já existe uma nova versão criada para este trimestre.');
        }

        $newVersionNumber = $rejectedReport->version + 1;

        return JubanReport::create([
            'year' => $rejectedReport->year,
            'quarter' => $rejectedReport->quarter,
            'version' => $newVersionNumber,
            'period_from' => $rejectedReport->period_from,
            'period_until' => $rejectedReport->period_until,
            'status' => JubanReportStatus::REABERTO,
            'snapshot' => [],
            'pdf_path' => null,
            'pdf_sha256' => null,
            'reopened_by' => $creator->id,
            'reopened_at' => now(),
            'reopen_reason' => $reason,
        ]);
    }
}
