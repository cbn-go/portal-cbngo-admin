<?php

namespace App\Services\FiscalCouncil;

use App\Enums\JubanReportOpinionMemberRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReportOpinion;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class FiscalCouncilOpinionTextService
{
    /**
     * Retorna o trimestre por extenso em pt-BR (ex.: "janeiro a março de 2026").
     */
    public function formatQuarterPeriod(int $year, int $quarter): string
    {
        return match ($quarter) {
            1 => "janeiro a março de {$year}",
            2 => "abril a junho de {$year}",
            3 => "julho a setembro de {$year}",
            4 => "outubro a dezembro de {$year}",
            default => "período do trimestre {$quarter} de {$year}",
        };
    }

    /**
     * Retorna a data em português por extenso (ex.: "17 de junho de 2026").
     */
    public function formatDateExtenso(CarbonInterface|string $date): string
    {
        $c = Carbon::parse($date)->locale('pt_BR');

        return "{$c->day} de {$c->translatedFormat('F')} de {$c->year}";
    }

    /**
     * Retorna a linha de identificação do conselheiro com tratamento (se houver) e nome.
     * Ex.: "Pr. Abílio Rocha Neto"
     */
    public function formatMemberDisplayName(FiscalCouncilMember $member): string
    {
        $name = $member->user->name;
        $title = trim((string) $member->title);

        if ($title !== '') {
            return "{$title} {$name}";
        }

        return $name;
    }

    /**
     * Monta o parágrafo principal oficial do parecer fiscal.
     */
    public function buildMainText(JubanReportOpinion $opinion): string
    {
        $opinion->loadMissing(['report', 'members.member.user']);

        $meetingDateStr = $this->formatDateExtenso($opinion->meeting_date);
        $meetingPlace = $opinion->meeting_place;

        $relatorMember = $opinion->members->first(fn ($m) => $m->role === JubanReportOpinionMemberRole::RELATOR);
        $relatorName = $relatorMember
            ? $this->formatMemberDisplayName($relatorMember->member)
            : 'Relator não informado';

        $vogais = $opinion->members->filter(fn ($m) => $m->role === JubanReportOpinionMemberRole::VOGAL);
        $vogaisNames = $vogais->map(function ($vm) {
            return $this->formatMemberDisplayName($vm->member);
        })->filter()->implode(', ');

        $entityFullName = config('portal.juban.full_name', 'Juventude Batista Nacional do Estado de Goiás');
        $entityAcronym = config('portal.juban.acronym', 'JUBAN');

        $quarterPeriod = $this->formatQuarterPeriod($opinion->report->year, $opinion->report->quarter);

        $conclusion = $opinion->result->getConclusionText();
        $recommendation = $opinion->result->getRecommendationText();

        return "O Conselho Fiscal, reunido no dia {$meetingDateStr}, na {$meetingPlace}, com a presença dos Conselheiros: Relator: {$relatorName} e Vogais: {$vogaisNames}, para examinar a documentação da {$entityFullName} ({$entityAcronym}), referente ao período de {$quarterPeriod}, concluiu que {$conclusion}, razão pela qual somos de parecer {$recommendation}.";
    }
}
