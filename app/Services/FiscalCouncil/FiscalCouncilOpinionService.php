<?php

namespace App\Services\FiscalCouncil;

use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Enums\JubanReportStatus;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportOpinionComment;
use App\Models\JubanReportOpinionHistory;
use App\Models\JubanReportOpinionMember;
use App\Models\User;
use App\Services\FiscalCouncil\Exceptions\FiscalCouncilOpinionException;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class FiscalCouncilOpinionService
{
    public function __construct(
        protected FiscalCouncilOpinionPdfService $pdfService
    ) {}

    /**
     * Valida quórum de 3 membros distintos com mandato ativo na data da reunião.
     *
     * @param  array{relator_member_id: int, vogal1_member_id: int, vogal2_member_id: int}  $memberIds
     * @return array{relator: FiscalCouncilMember, vogal1: FiscalCouncilMember, vogal2: FiscalCouncilMember}
     */
    private function validateMembers(array $memberIds, string $meetingDate): array
    {
        $idRelator = $memberIds['relator_member_id'];
        $idVogal1 = $memberIds['vogal1_member_id'];
        $idVogal2 = $memberIds['vogal2_member_id'];

        if (! $idRelator || ! $idVogal1 || ! $idVogal2) {
            throw new FiscalCouncilOpinionException('É necessário indicar exatamente 1 Relator e 2 Vogais para o parecer.');
        }

        $uniqueIds = array_unique([$idRelator, $idVogal1, $idVogal2]);
        if (count($uniqueIds) !== 3) {
            throw new FiscalCouncilOpinionException('Não é permitido repetir a mesma pessoa na composição do parecer.');
        }

        $checkDate = Carbon::parse($meetingDate);

        $mRelator = FiscalCouncilMember::with('user')->find($idRelator);
        $mVogal1 = FiscalCouncilMember::with('user')->find($idVogal1);
        $mVogal2 = FiscalCouncilMember::with('user')->find($idVogal2);

        foreach (['Relator' => $mRelator, '1º Vogal' => $mVogal1, '2º Vogal' => $mVogal2] as $roleName => $member) {
            if (! $member || ! $member->active || ! $member->isTermActive($checkDate)) {
                $name = $member?->user?->name ?? "ID {$member?->id}";
                throw new FiscalCouncilOpinionException("O membro indicado como {$roleName} ({$name}) não possui mandato ativo na data da reunião.");
            }
        }

        /** @var FiscalCouncilMember $mRelator */
        /** @var FiscalCouncilMember $mVogal1 */
        /** @var FiscalCouncilMember $mVogal2 */
        return [
            'relator' => $mRelator,
            'vogal1' => $mVogal1,
            'vogal2' => $mVogal2,
        ];
    }

    /**
     * Cria o rascunho inicial do parecer fiscal.
     *
     * @param  array{result: JubanReportOpinionResult|string, meeting_date: string, meeting_place?: string, city?: string, issued_on: string, reservations?: string|null}  $data
     * @param  array{relator_member_id: int, vogal1_member_id: int, vogal2_member_id: int}  $memberIds
     */
    public function createDraft(
        JubanReport $report,
        User $creator,
        array $data,
        array $memberIds
    ): JubanReportOpinion {
        $meetingDate = $data['meeting_date'];
        $validated = $this->validateMembers($memberIds, $meetingDate);

        $result = $data['result'] instanceof JubanReportOpinionResult
            ? $data['result']
            : JubanReportOpinionResult::from($data['result']);

        return DB::transaction(function () use ($report, $creator, $data, $result, $validated) {
            $opinion = JubanReportOpinion::query()->create([
                'report_id' => $report->id,
                'result' => $result,
                'status' => JubanReportOpinionStatus::DRAFT,
                'meeting_date' => $data['meeting_date'],
                'meeting_place' => $data['meeting_place'] ?? 'sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)',
                'city' => $data['city'] ?? 'Goiânia (GO)',
                'issued_on' => $data['issued_on'],
                'reservations' => $data['reservations'] ?? null,
                'drafted_by' => $creator->id,
            ]);

            JubanReportOpinionMember::query()->create([
                'opinion_id' => $opinion->id,
                'member_id' => $validated['relator']->id,
                'role' => JubanReportOpinionMemberRole::RELATOR,
                'position' => 1,
            ]);

            JubanReportOpinionMember::query()->create([
                'opinion_id' => $opinion->id,
                'member_id' => $validated['vogal1']->id,
                'role' => JubanReportOpinionMemberRole::VOGAL,
                'position' => 2,
            ]);

            JubanReportOpinionMember::query()->create([
                'opinion_id' => $opinion->id,
                'member_id' => $validated['vogal2']->id,
                'role' => JubanReportOpinionMemberRole::VOGAL,
                'position' => 3,
            ]);

            if ($report->status === JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL) {
                $report->update(['status' => JubanReportStatus::EM_REVISAO]);
            }

            return $opinion->load('members.member.user');
        });
    }

    /**
     * Atualiza dados do rascunho (apenas pelo Relator designado).
     *
     * @param  array{result?: JubanReportOpinionResult|string, meeting_date?: string, meeting_place?: string, city?: string, issued_on?: string, reservations?: string|null}  $data
     * @param  array{relator_member_id: int, vogal1_member_id: int, vogal2_member_id: int}|null  $memberIds
     */
    public function updateDraft(
        JubanReportOpinion $opinion,
        User $user,
        array $data,
        ?array $memberIds = null
    ): JubanReportOpinion {
        if (! $opinion->isDraft()) {
            throw new FiscalCouncilOpinionException('O parecer já foi emitido e não pode ser alterado após a emissão.');
        }

        if (! $opinion->canBeEditedBy($user)) {
            throw new AuthorizationException('Apenas o Relator designado pode editar o rascunho do parecer.');
        }

        return DB::transaction(function () use ($opinion, $data, $memberIds) {
            $meetingDate = $data['meeting_date'] ?? $opinion->meeting_date->toDateString();

            if ($memberIds !== null) {
                $validated = $this->validateMembers($memberIds, $meetingDate);

                $opinion->members()->delete();

                JubanReportOpinionMember::query()->create([
                    'opinion_id' => $opinion->id,
                    'member_id' => $validated['relator']->id,
                    'role' => JubanReportOpinionMemberRole::RELATOR,
                    'position' => 1,
                ]);

                JubanReportOpinionMember::query()->create([
                    'opinion_id' => $opinion->id,
                    'member_id' => $validated['vogal1']->id,
                    'role' => JubanReportOpinionMemberRole::VOGAL,
                    'position' => 2,
                ]);

                JubanReportOpinionMember::query()->create([
                    'opinion_id' => $opinion->id,
                    'member_id' => $validated['vogal2']->id,
                    'role' => JubanReportOpinionMemberRole::VOGAL,
                    'position' => 3,
                ]);
            }

            if (isset($data['result'])) {
                $data['result'] = $data['result'] instanceof JubanReportOpinionResult
                    ? $data['result']
                    : JubanReportOpinionResult::from($data['result']);
            }

            $opinion->update($data);

            return $opinion->fresh(['members.member.user']);
        });
    }

    /**
     * Adiciona um comentário ao rascunho do parecer.
     */
    public function addComment(
        JubanReportOpinion $opinion,
        User $user,
        string $comment
    ): JubanReportOpinionComment {
        $cleanComment = trim($comment);
        if ($cleanComment === '') {
            throw new FiscalCouncilOpinionException('O comentário não pode ser vazio.');
        }

        return JubanReportOpinionComment::query()->create([
            'opinion_id' => $opinion->id,
            'user_id' => $user->id,
            'comment' => $cleanComment,
        ]);
    }

    /**
     * Emite formalmente o parecer fiscal, gerando o PDF e gravando o hash SHA-256.
     */
    public function issueOpinion(
        JubanReportOpinion $opinion,
        User $relatorUser
    ): JubanReportOpinion {
        if (! $opinion->isDraft()) {
            throw new FiscalCouncilOpinionException('O parecer já foi emitido.');
        }

        if (! $opinion->canBeIssuedBy($relatorUser)) {
            throw new AuthorizationException('Apenas o Relator designado pode emitir o parecer.');
        }

        $opinion->loadMissing(['report.inquiries', 'members.member.user']);

        if ($opinion->members->count() !== 3) {
            throw new FiscalCouncilOpinionException('O parecer deve conter exatamente 1 Relator e 2 Vogais para ser emitido.');
        }

        if ($opinion->result->requiresReservations() && empty(trim((string) $opinion->reservations))) {
            throw new FiscalCouncilOpinionException('As ressalvas ou motivos devem ser informados quando o parecer for favorável com ressalvas ou desfavorável.');
        }

        if ($opinion->report->hasOpenInquiries()) {
            throw new FiscalCouncilOpinionException('Não é possível emitir o parecer com pedidos de esclarecimento em aberto.');
        }

        $stored = $this->pdfService->generateAndStore($opinion);

        $opinion->update([
            'status' => JubanReportOpinionStatus::ISSUED,
            'issued_at' => now(),
            'pdf_path' => $stored['pdf_path'],
            'pdf_sha256' => $stored['pdf_sha256'],
        ]);

        return $opinion->fresh(['members.member.user', 'report']);
    }

    /**
     * Cancela a emissão do parecer antes da primeira assinatura, arquivando no histórico.
     */
    public function cancelIssuance(
        JubanReportOpinion $opinion,
        User $relatorUser,
        ?string $reason = null
    ): JubanReportOpinion {
        if (! $opinion->isIssued()) {
            throw new FiscalCouncilOpinionException('Apenas pareceres emitidos podem ter a emissão cancelada.');
        }

        if (! $opinion->canBeCancelledBy($relatorUser)) {
            throw new AuthorizationException('Apenas o Relator designado pode cancelar a emissão do parecer.');
        }

        if ($opinion->hasSignatures()) {
            throw new FiscalCouncilOpinionException('Não é possível cancelar um parecer que já possui assinaturas registradas.');
        }

        return DB::transaction(function () use ($opinion, $relatorUser, $reason) {
            /** @var string $oldPdfPath */
            $oldPdfPath = $opinion->pdf_path;
            /** @var string $oldSha256 */
            $oldSha256 = $opinion->pdf_sha256;

            JubanReportOpinionHistory::query()->create([
                'opinion_id' => $opinion->id,
                'cancelled_by' => $relatorUser->id,
                'pdf_path' => $oldPdfPath,
                'pdf_sha256' => $oldSha256,
                'reason' => $reason,
                'cancelled_at' => now(),
            ]);

            $opinion->update([
                'status' => JubanReportOpinionStatus::DRAFT,
                'issued_at' => null,
                'pdf_path' => null,
                'pdf_sha256' => null,
            ]);

            return $opinion->fresh(['members.member.user', 'histories']);
        });
    }
}
