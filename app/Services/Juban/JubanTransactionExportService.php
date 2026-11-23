<?php

namespace App\Services\Juban;

use App\Models\JubanTransaction;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JubanTransactionExportService
{
    /**
     * Cabeçalho das colunas do arquivo exportado.
     *
     * @return array<int, string>
     */
    public function headers(): array
    {
        return [
            'Data',
            'Tipo',
            'Categoria',
            'Descrição',
            'Valor (R$)',
            'Valor Contábil',
            'Forma de Pagamento',
            'Documento / Ref',
            'Comprovante',
            'Lançado por',
        ];
    }

    /**
     * Mapeia um lançamento para a linha do CSV com formatações contábeis e BRL.
     *
     * @return array<int, string>
     */
    public function mapRow(JubanTransaction $transaction): array
    {
        $amount = (float) $transaction->amount;
        $isReceita = $transaction->isReceita();

        $valorBrl = $isReceita
            ? number_format($amount, 2, ',', '.')
            : '-'.number_format($amount, 2, ',', '.');

        $valorContabil = $isReceita
            ? number_format($amount, 2, '.', '')
            : '-'.number_format($amount, 2, '.', '');

        return [
            $transaction->occurred_on->format('d/m/Y'),
            $transaction->type->getLabel(),
            $transaction->category !== null ? $transaction->category->name : 'Sem categoria',
            $transaction->description,
            $valorBrl,
            $valorContabil,
            $transaction->payment_method !== null ? $transaction->payment_method->getLabel() : '',
            $transaction->document_ref ?? '',
            $transaction->hasReceipt() ? 'Sim' : 'Não',
            $transaction->creator !== null ? $transaction->creator->name : '-',
        ];
    }

    /**
     * Gera o conteúdo CSV completo com BOM UTF-8 e delimitador ';' (padrão Excel pt-BR).
     *
     * @param  Builder<JubanTransaction>  $query
     */
    public function generateCsvContent(Builder $query): string
    {
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            throw new \RuntimeException('Não foi possível abrir o buffer de memória para exportação CSV.');
        }

        // BOM UTF-8 para Excel pt-BR abrir com acentuação correta
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, $this->headers(), ';', '"', '\\');

        $exportQuery = (clone $query)->with(['category', 'creator']);

        foreach ($exportQuery->cursor() as $transaction) {
            /** @var JubanTransaction $transaction */
            fputcsv($handle, $this->mapRow($transaction), ';', '"', '\\');
        }

        rewind($handle);
        $content = (string) stream_get_contents($handle);
        fclose($handle);

        return $content;
    }

    /**
     * Cria a resposta de streaming para download imediato do arquivo CSV.
     *
     * @param  Builder<JubanTransaction>  $query
     */
    public function exportCsv(Builder $query, ?string $filename = null): StreamedResponse
    {
        $resolvedFilename = $filename ?? 'lancamentos-juban-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$resolvedFilename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            // BOM UTF-8
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $this->headers(), ';', '"', '\\');

            $exportQuery = (clone $query)->with(['category', 'creator']);

            foreach ($exportQuery->cursor() as $transaction) {
                /** @var JubanTransaction $transaction */
                fputcsv($handle, $this->mapRow($transaction), ';', '"', '\\');
            }

            fclose($handle);
        }, $resolvedFilename, $headers);
    }
}
