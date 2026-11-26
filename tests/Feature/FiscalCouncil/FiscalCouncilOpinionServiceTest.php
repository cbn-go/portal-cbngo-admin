<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportInquiryStatus;
use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportInquiry;
use App\Models\JubanReportOpinion;
use App\Models\User;
use App\Services\FiscalCouncil\Exceptions\FiscalCouncilOpinionException;
use App\Services\FiscalCouncil\FiscalCouncilOpinionService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiscalCouncilOpinionServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCouncilOpinionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = app(FiscalCouncilOpinionService::class);
    }

    /**
     * @return array{User, FiscalCouncilMember}
     */
    private function createCouncilMember(
        FiscalCouncilSeat $seat,
        string $name,
        ?string $title = null,
        ?string $qualification = null,
        bool $active = true
    ): array {
        $user = User::factory()->create([
            'name' => $name,
            'role' => UserRole::FISCAL_COUNCIL,
            'is_active' => $active,
        ]);

        $member = FiscalCouncilMember::query()->create([
            'user_id' => $user->id,
            'seat' => $seat,
            'title' => $title,
            'qualification' => $qualification,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => $active,
        ]);

        return [$user, $member];
    }

    public function test_active_fiscal_council_member_can_create_draft(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha', 'Pr.', 'Contador');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo', 'Dc.', 'Administrador');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::SUPLENTE_1, 'Marcos Fialho', null, 'Economista');

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'status' => JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
        ]);

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
                'reservations' => null,
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $this->assertInstanceOf(JubanReportOpinion::class, $opinion);
        $this->assertSame(JubanReportOpinionStatus::DRAFT, $opinion->status);
        $this->assertSame(JubanReportOpinionResult::APROVADO, $opinion->result);
        $this->assertEquals($uRelator->id, $opinion->drafted_by);
        $this->assertCount(3, $opinion->members);

        $this->assertDatabaseHas('juban_report_opinion_members', [
            'opinion_id' => $opinion->id,
            'member_id' => $mRelator->id,
            'role' => JubanReportOpinionMemberRole::RELATOR->value,
            'position' => 1,
        ]);
        $this->assertDatabaseHas('juban_report_opinion_members', [
            'opinion_id' => $opinion->id,
            'member_id' => $mVogal1->id,
            'role' => JubanReportOpinionMemberRole::VOGAL->value,
            'position' => 2,
        ]);
        $this->assertDatabaseHas('juban_report_opinion_members', [
            'opinion_id' => $opinion->id,
            'member_id' => $mVogal2->id,
            'role' => JubanReportOpinionMemberRole::VOGAL->value,
            'position' => 3,
        ]);
    }

    public function test_cannot_create_draft_with_duplicate_members(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');

        $report = JubanReport::factory()->create();

        $this->expectException(FiscalCouncilOpinionException::class);
        $this->expectExceptionMessage('Não é permitido repetir a mesma pessoa');

        $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mRelator->id, // Duplicado!
            ]
        );
    }

    public function test_cannot_create_draft_with_inactive_or_expired_member(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Inativo Silva', active: false);

        $report = JubanReport::factory()->create();

        $this->expectException(FiscalCouncilOpinionException::class);
        $this->expectExceptionMessage('não possui mandato ativo');

        $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );
    }

    public function test_only_designated_relator_can_update_draft(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho');

        $report = JubanReport::factory()->create();

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uVogal1, // Vogal iniciou o rascunho
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        // Vogal tenta alterar a redação do rascunho -> deve ser proibido!
        $this->expectException(AuthorizationException::class);
        $this->service->updateDraft($opinion, $uVogal1, [
            'result' => JubanReportOpinionResult::APROVADO_COM_RESSALVAS,
            'reservations' => 'Texto alterado pelo vogal',
        ]);
    }

    public function test_designated_relator_can_update_draft(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho');

        $report = JubanReport::factory()->create();

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $updated = $this->service->updateDraft($opinion, $uRelator, [
            'result' => JubanReportOpinionResult::APROVADO_COM_RESSALVAS,
            'reservations' => 'Ajuste contábil verificado e retificado.',
        ]);

        $this->assertSame(JubanReportOpinionResult::APROVADO_COM_RESSALVAS, $updated->result);
        $this->assertSame('Ajuste contábil verificado e retificado.', $updated->reservations);
    }

    public function test_members_can_comment_on_draft(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho');

        $report = JubanReport::factory()->create();

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $comment = $this->service->addComment($opinion, $uVogal1, 'Sugiro mencionar o saldo final conferido.');

        $this->assertDatabaseHas('juban_report_opinion_comments', [
            'opinion_id' => $opinion->id,
            'user_id' => $uVogal1->id,
            'comment' => 'Sugiro mencionar o saldo final conferido.',
        ]);
        $this->assertSame('Sugiro mencionar o saldo final conferido.', $comment->comment);
    }

    public function test_cannot_issue_opinion_without_reservations_if_result_requires_it(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho');

        $report = JubanReport::factory()->create();

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO_COM_RESSALVAS,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
                'reservations' => '', // Vazio!
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $this->expectException(FiscalCouncilOpinionException::class);
        $this->expectExceptionMessage('As ressalvas ou motivos devem ser informados');

        $this->service->issueOpinion($opinion, $uRelator);
    }

    public function test_cannot_issue_opinion_if_report_has_open_inquiries(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho');

        $report = JubanReport::factory()->create();

        JubanReportInquiry::query()->create([
            'report_id' => $report->id,
            'asked_by' => $uRelator->id,
            'asked_at' => now(),
            'question' => 'Falta recibo de combustivel',
            'status' => JubanReportInquiryStatus::ABERTO,
        ]);

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $this->expectException(FiscalCouncilOpinionException::class);
        $this->expectExceptionMessage('Não é possível emitir o parecer com pedidos de esclarecimento em aberto');

        $this->service->issueOpinion($opinion, $uRelator);
    }

    public function test_only_relator_can_issue_opinion(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho');

        $report = JubanReport::factory()->create();

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $this->expectException(AuthorizationException::class);
        $this->service->issueOpinion($opinion, $uVogal1);
    }

    public function test_issue_opinion_generates_pdf_computes_sha256_and_locks(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha', 'Pr.', 'Contador');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo', 'Dc.', 'Administrador');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho', 'Dca.', 'Advogada');

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
            'pdf_sha256' => hash('sha256', 'dummy-report-pdf-content'),
        ]);

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $issued = $this->service->issueOpinion($opinion, $uRelator);

        $this->assertSame(JubanReportOpinionStatus::ISSUED, $issued->status);
        $this->assertNotNull($issued->issued_at);
        $this->assertNotNull($issued->pdf_path);
        $this->assertNotNull($issued->pdf_sha256);
        $this->assertSame(64, strlen($issued->pdf_sha256));

        // Arquivo físico existe no disco local e o hash bate rigorosamente
        Storage::disk('local')->assertExists($issued->pdf_path);
        $fileBytes = Storage::disk('local')->get($issued->pdf_path);
        $this->assertSame($issued->pdf_sha256, hash('sha256', $fileBytes));

        // Tentar editar após emissão deve lançar exceção
        $this->expectException(FiscalCouncilOpinionException::class);
        $this->expectExceptionMessage('não pode ser alterado após a emissão');
        $this->service->updateDraft($issued, $uRelator, ['meeting_place' => 'Outro local']);
    }

    public function test_relator_can_cancel_issued_opinion_before_signatures(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha', 'Pr.', 'Contador');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo', 'Dc.', 'Administrador');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho', 'Dca.', 'Advogada');

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'pdf_sha256' => hash('sha256', 'dummy-report-pdf-content'),
        ]);

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $issued = $this->service->issueOpinion($opinion, $uRelator);
        $oldPdfPath = $issued->pdf_path;
        $oldSha256 = $issued->pdf_sha256;

        $cancelled = $this->service->cancelIssuance($issued, $uRelator, 'Necessidade de correção da data da reunião');

        $this->assertSame(JubanReportOpinionStatus::DRAFT, $cancelled->status);
        $this->assertNull($cancelled->issued_at);
        $this->assertNull($cancelled->pdf_path);
        $this->assertNull($cancelled->pdf_sha256);

        // O PDF anterior deve estar registrado no histórico
        $this->assertDatabaseHas('juban_report_opinion_histories', [
            'opinion_id' => $opinion->id,
            'cancelled_by' => $uRelator->id,
            'pdf_path' => $oldPdfPath,
            'pdf_sha256' => $oldSha256,
            'reason' => 'Necessidade de correção da data da reunião',
        ]);
    }

    public function test_non_relator_cannot_cancel_issued_opinion(): void
    {
        [$uRelator, $mRelator] = $this->createCouncilMember(FiscalCouncilSeat::RELATOR, 'Abílio Rocha');
        [$uVogal1, $mVogal1] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_1, 'Juarez Araújo');
        [$uVogal2, $mVogal2] = $this->createCouncilMember(FiscalCouncilSeat::VOGAL_2, 'Marilane Jacintho');

        $report = JubanReport::factory()->create(['pdf_sha256' => hash('sha256', 'dummy')]);

        $opinion = $this->service->createDraft(
            report: $report,
            creator: $uRelator,
            data: [
                'result' => JubanReportOpinionResult::APROVADO,
                'meeting_date' => '2026-06-17',
                'meeting_place' => 'sede da CBN-GO',
                'city' => 'Goiânia (GO)',
                'issued_on' => '2026-06-17',
            ],
            memberIds: [
                'relator_member_id' => $mRelator->id,
                'vogal1_member_id' => $mVogal1->id,
                'vogal2_member_id' => $mVogal2->id,
            ]
        );

        $issued = $this->service->issueOpinion($opinion, $uRelator);

        $this->expectException(AuthorizationException::class);
        $this->service->cancelIssuance($issued, $uVogal1, 'Tentativa não autorizada');
    }
}
