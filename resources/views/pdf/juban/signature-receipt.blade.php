<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Comprovante de Assinaturas - Relatório Q{{ $report->quarter }}/{{ $report->year }} JUBAN</title>
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
        .section-title {
            font-size: 11pt;
            font-weight: bold;
            color: #1e3a8a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-top: 18px;
            margin-bottom: 10px;
            text-transform: uppercase;
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
        .sig-card {
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-left: 4px solid #16a34a;
            border-radius: 4px;
            padding: 12px 16px;
            margin-bottom: 14px;
        }
        .sig-role {
            font-size: 11pt;
            font-weight: bold;
            color: #111827;
            margin-bottom: 6px;
        }
        .hash-code {
            font-family: monospace;
            font-size: 8pt;
            color: #0f172a;
            background-color: #f1f5f9;
            padding: 3px 6px;
            border-radius: 3px;
            word-break: break-all;
            display: inline-block;
        }
        .footer {
            margin-top: 30px;
            border-top: 1px solid #e2e8f0;
            padding-top: 10px;
            font-size: 8pt;
            color: #64748b;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="header-title">Convenção Batista Nacional - Estado de Goiás</h1>
        <div class="header-sub">Juventude Batista Nacional (JUBAN) &bull; Comprovante Oficial de Assinaturas Digitais</div>
    </div>

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Relatório:</td>
                <td><strong>Q{{ $report->quarter }}/{{ $report->year }}</strong> ({{ $report->period_from->format('d/m/Y') }} a {{ $report->period_until->format('d/m/Y') }})</td>
            </tr>
            <tr>
                <td class="meta-label">Status:</td>
                <td>{{ $report->status->getLabel() }}</td>
            </tr>
            <tr>
                <td class="meta-label">Fechado em:</td>
                <td>{{ $report->closed_at ? $report->closed_at->format('d/m/Y H:i:s') : 'N/A' }} por {{ $report->closedBy?->name ?? 'Sistema' }}</td>
            </tr>
            <tr>
                <td class="meta-label">Hash do Relatório:</td>
                <td><span class="hash-code">{{ $report->pdf_sha256 }}</span></td>
            </tr>
        </table>
    </div>

    <div class="section-title">Registro das Assinaturas Criptográficas (FIDO2 / WebAuthn)</div>

    @forelse ($report->signatures as $signature)
        <div class="sig-card">
            <div class="sig-role">
                {{ $signature->role->getLabel() }} &bull; Assinatura Confirmada
            </div>
            <table class="meta-table">
                <tr>
                    <td class="meta-label" style="width: 28%;">Assinante:</td>
                    <td><strong>{{ $signature->user->name }}</strong> ({{ $signature->user->email }})</td>
                </tr>
                <tr>
                    <td class="meta-label">Data e Hora:</td>
                    <td>{{ $signature->signed_at->format('d/m/Y \à\s H:i:s') }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Dispositivo / Passkey:</td>
                    <td>{{ $signature->passkey->name }} (ID: {{ substr($signature->passkey->credential_id, 0, 16) }}...)</td>
                </tr>
                <tr>
                    <td class="meta-label">IP e Navegador:</td>
                    <td>{{ $signature->ip ?? 'N/A' }} &bull; {{ $signature->user_agent ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td class="meta-label">Challenge SHA-256:</td>
                    <td><span class="hash-code">{{ $signature->challenge }}</span></td>
                </tr>
            </table>
        </div>
    @empty
        <p style="color: #64748b; font-style: italic;">Nenhuma assinatura digital registrada para este relatório até o momento.</p>
    @endforelse

    <div class="footer">
        Documento gerado eletronicamente em {{ $generatedAt->format('d/m/Y H:i:s') }} através do Portal Administrativo CBN-GO.<br>
        A autenticidade deste comprovante e do relatório pode ser verificada de forma independente pelo hash SHA-256.
    </div>
</body>
</html>
