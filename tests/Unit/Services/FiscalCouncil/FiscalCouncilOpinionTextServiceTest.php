<?php

namespace Tests\Unit\Services\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportOpinionMember;
use App\Models\User;
use App\Services\FiscalCouncil\FiscalCouncilOpinionTextService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FiscalCouncilOpinionTextServiceTest extends TestCase
{
    use RefreshDatabase;

    private FiscalCouncilOpinionTextService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(FiscalCouncilOpinionTextService::class);
    }

    public function test_format_quarter_period_for_all_quarters(): void
    {
        $this->assertSame('janeiro a março de 2026', $this->service->formatQuarterPeriod(2026, 1));
        $this->assertSame('abril a junho de 2026', $this->service->formatQuarterPeriod(2026, 2));
        $this->assertSame('julho a setembro de 2026', $this->service->formatQuarterPeriod(2026, 3));
        $this->assertSame('outubro a dezembro de 2026', $this->service->formatQuarterPeriod(2026, 4));
    }

    public function test_format_date_extenso(): void
    {
        $date = Carbon::parse('2026-06-17');
        $this->assertSame('17 de junho de 2026', $this->service->formatDateExtenso($date));
    }

    public function test_format_member_display_name_with_and_without_title(): void
    {
        $user1 = User::factory()->create(['name' => 'Abílio Rocha Neto']);
        $member1 = FiscalCouncilMember::factory()->create([
            'user_id' => $user1->id,
            'title' => 'Pr.',
        ]);

        $user2 = User::factory()->create(['name' => 'Marcos Fialho Silva']);
        $member2 = FiscalCouncilMember::factory()->create([
            'user_id' => $user2->id,
            'title' => null,
        ]);

        $this->assertSame('Pr. Abílio Rocha Neto', $this->service->formatMemberDisplayName($member1));
        $this->assertSame('Marcos Fialho Silva', $this->service->formatMemberDisplayName($member2));
    }

    public function test_build_main_text_for_approved_result_matches_canonical_model(): void
    {
        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'period_from' => '2026-01-01',
            'period_until' => '2026-03-31',
        ]);

        $uRelator = User::factory()->create(['name' => 'Abílio Rocha Neto']);
        $uVogal1 = User::factory()->create(['name' => 'Juarez Raimundo de Araújo']);
        $uVogal2 = User::factory()->create(['name' => 'Marilane Cristina Jacintho e Braga']);

        $mRelator = FiscalCouncilMember::factory()->create([
            'user_id' => $uRelator->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'title' => 'Pr.',
            'qualification' => 'Técnico em contabilidade',
        ]);
        $mVogal1 = FiscalCouncilMember::factory()->create([
            'user_id' => $uVogal1->id,
            'seat' => FiscalCouncilSeat::VOGAL_1,
            'title' => 'Dc.',
            'qualification' => 'Administrador',
        ]);
        $mVogal2 = FiscalCouncilMember::factory()->create([
            'user_id' => $uVogal2->id,
            'seat' => FiscalCouncilSeat::VOGAL_2,
            'title' => 'Dca.',
            'qualification' => 'Bacharel em Direito',
        ]);

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::DRAFT,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $uRelator->id,
        ]);

        JubanReportOpinionMember::query()->create([
            'opinion_id' => $opinion->id,
            'member_id' => $mRelator->id,
            'role' => JubanReportOpinionMemberRole::RELATOR,
            'position' => 1,
        ]);
        JubanReportOpinionMember::query()->create([
            'opinion_id' => $opinion->id,
            'member_id' => $mVogal1->id,
            'role' => JubanReportOpinionMemberRole::VOGAL,
            'position' => 2,
        ]);
        JubanReportOpinionMember::query()->create([
            'opinion_id' => $opinion->id,
            'member_id' => $mVogal2->id,
            'role' => JubanReportOpinionMemberRole::VOGAL,
            'position' => 3,
        ]);

        $opinion->load('members.member.user');

        $mainText = $this->service->buildMainText($opinion);

        $expected = 'O Conselho Fiscal, reunido no dia 17 de junho de 2026, na sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO), com a presença dos Conselheiros: Relator: Pr. Abílio Rocha Neto e Vogais: Dc. Juarez Raimundo de Araújo, Dca. Marilane Cristina Jacintho e Braga, para examinar a documentação da Juventude Batista Nacional do Estado de Goiás (JUBAN), referente ao período de janeiro a março de 2026, concluiu que a documentação se encontra em perfeita harmonia com os lançamentos contábeis, razão pela qual somos de parecer favorável que o balancete seja aprovado pelo CONPLEX desta.';

        $this->assertSame($expected, $mainText);
    }

    public function test_build_main_text_for_approved_with_reservations(): void
    {
        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 2,
        ]);

        $uRelator = User::factory()->create(['name' => 'Abílio Rocha Neto']);
        $uVogal1 = User::factory()->create(['name' => 'Juarez Raimundo de Araújo']);
        $uVogal2 = User::factory()->create(['name' => 'Marilane Cristina Jacintho e Braga']);

        $mRelator = FiscalCouncilMember::factory()->create(['user_id' => $uRelator->id, 'seat' => FiscalCouncilSeat::RELATOR, 'title' => 'Pr.']);
        $mVogal1 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal1->id, 'seat' => FiscalCouncilSeat::VOGAL_1, 'title' => 'Dc.']);
        $mVogal2 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal2->id, 'seat' => FiscalCouncilSeat::VOGAL_2, 'title' => 'Dca.']);

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO_COM_RESSALVAS,
            'status' => JubanReportOpinionStatus::DRAFT,
            'meeting_date' => '2026-07-20',
            'meeting_place' => 'sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-07-20',
            'reservations' => 'Pequena divergência sanada na prestação de contas.',
            'drafted_by' => $uRelator->id,
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal1->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        $opinion->load('members.member.user');

        $mainText = $this->service->buildMainText($opinion);

        $this->assertStringContainsString('concluiu que a documentação se encontra em harmonia com os lançamentos contábeis, com as ressalvas abaixo, razão pela qual somos de parecer favorável, com ressalvas, que o balancete seja aprovado pelo CONPLEX desta.', $mainText);
    }

    public function test_build_main_text_for_reproved(): void
    {
        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 3,
        ]);

        $uRelator = User::factory()->create(['name' => 'Abílio Rocha Neto']);
        $uVogal1 = User::factory()->create(['name' => 'Juarez Raimundo de Araújo']);
        $uVogal2 = User::factory()->create(['name' => 'Marilane Cristina Jacintho e Braga']);

        $mRelator = FiscalCouncilMember::factory()->create(['user_id' => $uRelator->id, 'seat' => FiscalCouncilSeat::RELATOR, 'title' => 'Pr.']);
        $mVogal1 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal1->id, 'seat' => FiscalCouncilSeat::VOGAL_1, 'title' => 'Dc.']);
        $mVogal2 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal2->id, 'seat' => FiscalCouncilSeat::VOGAL_2, 'title' => 'Dca.']);

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::REPROVADO,
            'status' => JubanReportOpinionStatus::DRAFT,
            'meeting_date' => '2026-10-15',
            'meeting_place' => 'sede da Convenção Batista Nacional do Estado de Goiás em Goiânia (GO)',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-10-15',
            'reservations' => 'Ausência de comprovantes de despesas significativas.',
            'drafted_by' => $uRelator->id,
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal1->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        $opinion->load('members.member.user');

        $mainText = $this->service->buildMainText($opinion);

        $this->assertStringContainsString('concluiu que a documentação não se encontra em harmonia com os lançamentos contábeis, pelos motivos abaixo, razão pela qual somos de parecer desfavorável à aprovação do balancete pelo CONPLEX desta.', $mainText);
    }
}
