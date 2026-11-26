<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>PARECER FISCAL ASSINADO - Q{{ $opinion->report->quarter }}/{{ $opinion->report->year }}</title>
    <style>
        @page {
            margin: 20mm 18mm 20mm 18mm;
            size: a4 portrait;
        }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10.5pt;
            color: #111827;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 24px;
        }
        .title {
            font-size: 14pt;
            font-weight: bold;
            text-decoration: underline;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 0;
        }
        .content {
            text-align: justify;
            text-justify: inter-word;
            margin-bottom: 20px;
            text-indent: 36px;
        }
        .reservations-box {
            margin-top: 14px;
            margin-bottom: 20px;
            text-align: justify;
        }
        .reservations-title {
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .date-line {
            text-align: right;
            margin-top: 20px;
            margin-bottom: 30px;
        }
        .signatures-container {
            width: 100%;
            margin-top: 20px;
            page-break-inside: avoid;
        }
        .signature-item {
            margin-bottom: 22px;
        }
        .signature-line {
            display: inline-block;
            border-bottom: 1px solid #111827;
            width: 320px;
            margin-left: 10px;
        }
        .signature-info {
            margin-left: 90px;
            font-size: 10pt;
            line-height: 1.3;
        }
        .signature-role {
            font-weight: bold;
            display: inline-block;
            width: 80px;
        }
        .footer {
            border-top: 1px solid #cbd5e1;
            padding-top: 6px;
            font-size: 8pt;
            color: #64748b;
            line-height: 1.3;
            margin-top: 20px;
        }
        .inquiries-box {
            margin-top: 10px;
            padding: 6px 10px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            font-size: 8pt;
        }
        .inquiries-title {
            font-weight: bold;
            margin-bottom: 2px;
            color: #334155;
        }

        /* Manifesto Page Styles */
        .page-break {
            page-break-before: always;
        }
        .manifesto-header {
            border-bottom: 2px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .manifesto-title {
            font-size: 13pt;
            font-weight: bold;
            color: #1e3a8a;
            margin: 0;
            text-transform: uppercase;
        }
        .manifesto-sub {
            font-size: 9pt;
            color: #4b5563;
            margin-top: 3px;
        }
        .meta-box {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 8px 12px;
            margin-bottom: 14px;
            font-size: 9pt;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
        }
        .meta-table td {
            padding: 3px 5px;
            font-size: 8.5pt;
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
            padding: 10px 14px;
            margin-bottom: 12px;
            page-break-inside: avoid;
        }
        .sig-card.divergent {
            border-left-color: #f59e0b;
        }
        .sig-role {
            font-size: 10pt;
            font-weight: bold;
            color: #111827;
            margin-bottom: 4px;
        }
        .divergent-box {
            background-color: #fffbeb;
            border: 1px solid #fef3c7;
            color: #92400e;
            padding: 6px 10px;
            margin-top: 6px;
            margin-bottom: 6px;
            border-radius: 4px;
            font-size: 8.5pt;
        }
        .hash-code {
            font-family: monospace;
            font-size: 7.5pt;
            color: #0f172a;
            background-color: #f1f5f9;
            padding: 2px 4px;
            border-radius: 2px;
            word-break: break-all;
            display: inline-block;
        }
    </style>
</head>
<body>
    {{-- Página 1: Conteúdo Original do Parecer Fiscal --}}
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
                        <div style="color: #4b5563; font-size: 9pt;">{{ $member->qualification }}</div>
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

    {{-- Página 2: Manifesto de Assinaturas Eletrônicas (WebAuthn / Passkey) --}}
    <div class="page-break"></div>

    <div class="manifesto-header">
        <h2 class="manifesto-title">Termo de Encerramento e Manifesto de Assinaturas Eletrônicas</h2>
        <div class="manifesto-sub">Conselho Fiscal &bull; Convenção Batista Nacional do Estado de Goiás (CBN-GO)</div>
    </div>

    <div class="meta-box">
        <table class="meta-table">
            <tr>
                <td class="meta-label">Relatório Referenciado:</td>
                <td>JUBAN Q{{ $opinion->report->quarter }}/{{ $opinion->report->year }} (v{{ $opinion->report->version }})</td>
            </tr>
            <tr>
                <td class="meta-label">SHA-256 do Relatório:</td>
                <td><span class="hash-code">{{ $opinion->report->pdf_sha256 }}</span></td>
            </tr>
            <tr>
                <td class="meta-label">Parecer Fiscal:</td>
                <td>ID #{{ $opinion->id }} &bull; Data de Reunião: {{ $opinion->meeting_date->format('d/m/Y') }} &bull; Emissão: {{ $opinion->issued_on->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td class="meta-label">Resultado Final:</td>
                <td><strong>{{ $opinion->result->getLabel() }}</strong> (Quórum regimental de 3 votos concluído)</td>
            </tr>
            <tr>
                <td class="meta-label">SHA-256 do Parecer Emitido:</td>
                <td><span class="hash-code">{{ $opinion->pdf_sha256 }}</span></td>
            </tr>
            <tr>
                <td class="meta-label">Amparo Normativo:</td>
                <td>Assinatura Eletrônica Avançada / Qualificada com WebAuthn Level 3 (FIDO2) conforme Lei Federal nº 14.063/2020.</td>
            </tr>
        </table>
    </div>

    <div>
        @foreach ($opinion->signatures as $signature)
            @php
                $memberRow = $opinion->members->firstWhere('member.user_id', $signature->user_id);
                $member = $memberRow?->member;
                $title = trim((string) $member?->title);
                $displayName = $title !== '' ? "{$title} {$signature->user->name}" : $signature->user->name;
            @endphp
            <div class="sig-card {{ $signature->divergent_vote ? 'divergent' : '' }}">
                <div class="sig-role">
                    {{ $signature->role->getLabel() }}: {{ $displayName }}
                    @if ($member?->qualification)
                        <span style="font-weight: normal; color: #4b5563; font-size: 8.5pt;">({{ $member->qualification }})</span>
                    @endif
                </div>

                <div style="font-size: 8.5pt; color: #374151; margin-bottom: 4px;">
                    <strong>Assinado em:</strong> {{ $signature->signed_at->format('d/m/Y \à\s H:i:s') }} (Horário de Brasília) &bull;
                    <strong>Passkey:</strong> {{ $signature->passkey?->name ?? 'Passkey FIDO2' }} &bull;
                    <strong>UV (Biometria/PIN):</strong> <span style="color: #16a34a; font-weight: bold;">Validado</span>
                </div>

                @if ($signature->ip || $signature->user_agent)
                    <div style="font-size: 8pt; color: #64748b; margin-bottom: 6px;">
                        @if ($signature->ip) IP: {{ $signature->ip }} &bull; @endif
                        @if ($signature->user_agent) Dispositivo: {{ \Illuminate\Support\Str::limit($signature->user_agent, 75) }} @endif
                    </div>
                @endif

                @if ($signature->divergent_vote)
                    <div class="divergent-box">
                        <strong>Voto Divergente / Ressalva Registrada:</strong><br>
                        {{ $signature->divergent_vote }}
                    </div>
                @endif

                <div style="margin-top: 4px; font-size: 7.5pt; color: #475569;">
                    <div><strong>Nonce:</strong> <span class="hash-code">{{ $signature->nonce }}</span></div>
                    <div style="margin-top: 2px;"><strong>Challenge Canônico:</strong> <span class="hash-code">{{ $signature->challenge }}</span></div>
                    <div style="margin-top: 2px;"><strong>Assinatura (WebAuthn):</strong> <span class="hash-code">{{ \Illuminate\Support\Str::limit($signature->signature, 60) }}</span></div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="footer" style="text-align: center; margin-top: 20px;">
        Documento gerado pelo Portal CBN-GO em {{ now()->format('d/m/Y \à\s H:i:s') }}.<br>
        A autenticidade e higidez deste documento podem ser verificadas independentemente através do Verificador de Assinaturas do sistema.
    </div>
</body>
</html>
