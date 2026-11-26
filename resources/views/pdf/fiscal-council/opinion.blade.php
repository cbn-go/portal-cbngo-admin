<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>PARECER FISCAL - Q{{ $opinion->report->quarter }}/{{ $opinion->report->year }}</title>
    <style>
        @page {
            margin: 25mm 20mm 25mm 20mm;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11pt;
            color: #111827;
            line-height: 1.6;
        }
        .header {
            text-align: center;
            margin-bottom: 28px;
        }
        .title {
            font-size: 15pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .content {
            text-align: justify;
            text-justify: inter-word;
            margin-bottom: 24px;
            text-indent: 40px;
        }
        .reservations-box {
            margin-top: 18px;
            margin-bottom: 24px;
            text-align: justify;
        }
        .reservations-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .date-line {
            text-align: right;
            margin-top: 24px;
            margin-bottom: 40px;
        }
        .signatures-container {
            width: 100%;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .signature-item {
            margin-bottom: 30px;
        }
        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #111827;
            width: 350px;
            margin-left: 10px;
        }
        .signature-info {
            margin-left: 90px;
            font-size: 10.5pt;
            line-height: 1.3;
        }
        .signature-role {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            font-size: 8pt;
            color: #64748b;
            line-height: 1.3;
        }
        .inquiries-box {
            margin-top: 15px;
            padding: 8px 12px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 8.5pt;
        }
        .inquiries-title {
            font-weight: bold;
            margin-bottom: 4px;
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">PARECER FISCAL</h1>
    </div>

    <div class="content">
        {{ $mainText }}
    </div>

    @if ($opinion->reservations)
        <div class="reservations-box">
            <div class="reservations-title">{{ $opinion->result === \App\Enums\JubanReportOpinionResult::REPROVADO ? 'Motivos:' : 'Ressalvas:' }}</div>
            <div>{{ $opinion->reservations }}</div>
        </div>
    @endif

    <div class="date-line">
        {{ $opinion->city }}, {{ $issuedOnText }}.
    </div>

    <div class="signatures-container">
        @foreach ($opinion->members as $m)
            @php
                $member = $m->member;
                $user = $member?->user;
                $title = trim((string) $member?->title);
                $nameWithTitle = $title !== '' ? "{$title} {$user?->name}" : ($user?->name ?? '');
                $roleLabel = $m->role === \App\Enums\JubanReportOpinionMemberRole::RELATOR ? 'Relator:' : 'Vogal:';
            @endphp
            <div class="signature-item">
                <span class="signature-role">{{ $roleLabel }}</span>
                <span class="signature-line"></span>
                <div class="signature-info">
                    <div>{{ $nameWithTitle }}</div>
                    @if ($member?->qualification)
                        <div style="color: #4b5563; font-size: 9.5pt;">{{ $member->qualification }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="footer">
        <div>
            <strong>Referência:</strong> Relatório JUBAN Q{{ $opinion->report->quarter }}/{{ $opinion->report->year }} 
            @if ($opinion->report->version > 1) (v{{ $opinion->report->version }}) @endif
            &bull; <strong>Hash SHA-256 do Relatório:</strong> <span style="font-family: monospace;">{{ $opinion->report->pdf_sha256 ?? 'Pendente' }}</span>
        </div>
        @if ($opinion->report->inquiries && $opinion->report->inquiries->isNotEmpty())
            <div class="inquiries-box">
                <div class="inquiries-title">Pedidos de Esclarecimento Registrados ({{ $opinion->report->inquiries->count() }}):</div>
                @foreach ($opinion->report->inquiries as $inq)
                    <div>
                        &bull; [{{ $inq->status->getLabel() }}] {{ \Illuminate\Support\Str::limit($inq->question, 90) }}
                        @if ($inq->isAnswered() && $inq->answer)
                            — <em>Resp: {{ \Illuminate\Support\Str::limit($inq->answer, 80) }}</em>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>
