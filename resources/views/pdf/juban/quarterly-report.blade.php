<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Relatório Financeiro Trimestral JUBAN - Q{{ $report->quarter }}/{{ $report->year }}</title>
    <style>
        @page {
            margin: 20mm 15mm 20mm 15mm;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #1f2937;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 12px;
            margin-bottom: 16px;
        }
        .header-title {
            font-size: 15pt;
            font-weight: bold;
            color: #111827;
            margin: 0;
            text-transform: uppercase;
        }
        .header-sub {
            font-size: 10pt;
            color: #4b5563;
            margin-top: 3px;
        }
        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px 14px;
            margin-bottom: 18px;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 4px 6px;
            font-size: 9.5pt;
        }
        .meta-label {
            font-weight: bold;
            color: #475569;
            width: 25%;
        }
        .summary-cards {
            width: 100%;
            margin-bottom: 20px;
        }
        .summary-card {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            padding: 10px;
            text-align: center;
        }
        .card-receita { border-left: 4px solid #16a34a; }
        .card-despesa { border-left: 4px solid #dc2626; }
        .card-saldo   { border-left: 4px solid #2563eb; }
        .card-title {
            font-size: 8.5pt;
            text-transform: uppercase;
            font-weight: bold;
            color: #64748b;
        }
        .card-val {
            font-size: 13pt;
            font-weight: bold;
            margin-top: 4px;
        }
        .val-receita { color: #16a34a; }
        .val-despesa { color: #dc2626; }
        .val-saldo   { color: #2563eb; }
        
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 8px;
            text-transform: uppercase;
        }
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 16px;
        }
        .data-table th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: bold;
            font-size: 8.5pt;
            text-align: left;
            padding: 6px 8px;
            border-bottom: 1px solid #cbd5e1;
        }
        .data-table td {
            padding: 5px 8px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 8.5pt;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .page-break { page-break-after: always; }
        
        .hash-box {
            margin-top: 25px;
            background-color: #f8fafc;
            border: 1px dashed #94a3b8;
            padding: 10px 14px;
            font-size: 8pt;
            color: #334155;
            word-break: break-all;
        }
        .signatures {
            margin-top: 35px;
            width: 100%;
            page-break-inside: avoid;
        }
        .sig-box {
            text-align: center;
            width: 46%;
            padding: 10px;
        }
        .sig-line {
            border-top: 1px solid #475569;
            margin-top: 45px;
            padding-top: 5px;
            font-size: 9pt;
            font-weight: bold;
        }
        .sig-role {
            font-size: 8pt;
            color: #64748b;
        }
    </style>
</head>
<body>

    <div class="header">
        <table style="width: 100%;">
            <tr>
                <td>
                    <h1 class="header-title">Convenção Batista Nacional de Goiás</h1>
                    <div class="header-sub">JUBAN — Juventude Batista Nacional (Secional Goiás)</div>
                    <div class="header-sub"><strong>Livro-Caixa Digital — Relatório Financeiro Trimestral (Q{{ $report->quarter }} / {{ $report->year }})</strong></div>
                </td>
                <td style="text-align: right; vertical-align: top;">
                    <div style="font-size: 8pt; color: #64748b;">Emissão oficial: {{ now()->format('d/m/Y H:i') }}</div>
                    <div style="font-size: 8pt; color: #64748b;">Status: {{ $report->status->getLabel() }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Período Contábil:</td>
                <td>{{ \Carbon\Carbon::parse($report->period_from)->format('d/m/Y') }} a {{ \Carbon\Carbon::parse($report->period_until)->format('d/m/Y') }}</td>
                <td class="meta-label">Exercício:</td>
                <td>Ano {{ $report->year }} — Trimestre {{ $report->quarter }}</td>
            </tr>
            <tr>
                <td class="meta-label">Fechado por:</td>
                <td>{{ $report->closedBy?->name ?? 'Tesouraria JUBAN' }}</td>
                <td class="meta-label">Data de Fechamento:</td>
                <td>{{ $report->closed_at ? $report->closed_at->format('d/m/Y \à\s H:i:s') : 'N/A' }}</td>
            </tr>
        </table>
    </div>

    {{-- Cards Resumo --}}
    <table class="summary-cards" style="width: 100%;">
        <tr>
            <td style="width: 32%; padding-right: 8px;">
                <div class="summary-card card-receita">
                    <div class="card-title">Total de Receitas</div>
                    <div class="card-val val-receita">R$ {{ number_format($report->snapshot['summary']['receitas'] ?? 0, 2, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 32%; padding: 0 4px;">
                <div class="summary-card card-despesa">
                    <div class="card-title">Total de Despesas</div>
                    <div class="card-val val-despesa">R$ {{ number_format($report->snapshot['summary']['despesas'] ?? 0, 2, ',', '.') }}</div>
                </div>
            </td>
            <td style="width: 32%; padding-left: 8px;">
                <div class="summary-card card-saldo">
                    <div class="card-title">Saldo Líquido</div>
                    <div class="card-val val-saldo">R$ {{ number_format($report->snapshot['summary']['saldo'] ?? 0, 2, ',', '.') }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Demonstrativo por Categoria --}}
    <div class="section-title">1. Demonstrativo por Categoria Contábil</div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Categoria</th>
                <th>Natureza</th>
                <th class="text-right">Total (R$)</th>
                <th class="text-right">Proporção</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->snapshot['categories'] ?? [] as $cat)
                <tr>
                    <td>{{ $cat['name'] }}</td>
                    <td>{{ ucfirst($cat['type'] ?? '') }}</td>
                    <td class="text-right">R$ {{ number_format($cat['total'], 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format($cat['percent'], 1, ',', '.') }}%</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="text-center" style="color: #64748b;">Nenhuma movimentação no período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Despesas Mensais --}}
    @if (!empty($report->snapshot['monthly_despesas']))
        <div class="section-title">2. Distribuição Mensal de Gastos</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Mês / Ano</th>
                    <th class="text-right">Despesas Realizadas (R$)</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($report->snapshot['monthly_despesas'] as $m)
                    <tr>
                        <td>{{ $m['label'] }}</td>
                        <td class="text-right">R$ {{ number_format($m['amount'], 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    {{-- Rol Cronológico de Transações --}}
    <div class="section-title" style="page-break-before: auto;">3. Relação Cronológica Completa de Lançamentos ({{ count($report->snapshot['transactions'] ?? []) }})</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 12%;">Data</th>
                <th style="width: 10%;">Tipo</th>
                <th style="width: 22%;">Categoria</th>
                <th style="width: 38%;">Descrição</th>
                <th style="width: 18%;" class="text-right">Valor (R$)</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report->snapshot['transactions'] ?? [] as $t)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($t['occurred_on'])->format('d/m/Y') }}</td>
                    <td>
                        <span style="font-weight: bold; color: {{ $t['type'] === 'receita' ? '#16a34a' : '#dc2626' }};">
                            {{ ucfirst($t['type']) }}
                        </span>
                    </td>
                    <td>{{ $t['category_name'] }}</td>
                    <td>
                        {{ $t['description'] }}
                        @if (!empty($t['document_ref']))
                            <div style="font-size: 7.5pt; color: #64748b;">Doc: {{ $t['document_ref'] }}</div>
                        @endif
                    </td>
                    <td class="text-right" style="font-weight: bold; color: {{ $t['type'] === 'receita' ? '#16a34a' : '#dc2626' }};">
                        {{ $t['type'] === 'receita' ? '+' : '-' }} R$ {{ number_format($t['amount'], 2, ',', '.') }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center" style="color: #64748b;">Nenhum lançamento registrado no trimestre.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    {{-- Hash Criptográfico e Autenticidade --}}
    <div class="hash-box">
        <strong>AUTENTICIDADE E INTEGRIDADE DO DOCUMENTO</strong><br>
        Este documento contém o snapshot imutável do livro-caixa digital da JUBAN para o período indicado.<br>
        <strong>Hash Criptográfico (SHA-256):</strong> {{ $report->pdf_sha256 ?? 'Pendente de cálculo' }}
    </div>

    {{-- Assinaturas Digitais WebAuthn --}}
    <table class="signatures">
        <tr>
            <td class="sig-box">
                <div class="sig-line">
                    Tesoureiro(a) da JUBAN
                </div>
                <div class="sig-role">
                    @if ($report->status === \App\Enums\JubanReportStatus::AGUARDANDO_PRESIDENCIA || $report->status === \App\Enums\JubanReportStatus::ASSINADO)
                        Assinado digitalmente via Passkey FIDO2
                    @else
                        Aguardando assinatura digital
                    @endif
                </div>
            </td>
            <td style="width: 8%;"></td>
            <td class="sig-box">
                <div class="sig-line">
                    Presidente da JUBAN
                </div>
                <div class="sig-role">
                    @if ($report->status === \App\Enums\JubanReportStatus::ASSINADO)
                        Assinado digitalmente via Passkey FIDO2
                    @else
                        Aguardando assinatura digital
                    @endif
                </div>
            </td>
        </tr>
    </table>

</body>
</html>
