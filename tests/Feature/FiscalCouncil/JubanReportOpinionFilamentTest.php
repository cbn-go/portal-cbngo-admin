<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Enums\JubanReportStatus;
use App\Enums\UserRole;
use App\Filament\FiscalCouncil\Resources\JubanReportResource\Pages\ViewJubanReport;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportOpinionMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class JubanReportOpinionFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('conselho-fiscal'));
    }

    public function test_view_juban_report_shows_draft_opinion_button_when_no_opinion_exists(): void
    {
        $fiscalUser = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'is_active' => true]);
        FiscalCouncilMember::factory()->create([
            'user_id' => $fiscalUser->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'active' => true,
        ]);

        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::AGUARDANDO_CONSELHO_FISCAL,
        ]);

        $this->actingAs($fiscalUser);

        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionVisible('draftOpinion');
    }

    public function test_view_juban_report_shows_edit_and_issue_to_relator_and_only_comment_to_vogal(): void
    {
        $uRelator = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Abílio Rocha']);
        $uVogal = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Juarez Araújo']);

        $mRelator = FiscalCouncilMember::factory()->create(['user_id' => $uRelator->id, 'seat' => FiscalCouncilSeat::RELATOR, 'active' => true]);
        $mVogal = FiscalCouncilMember::factory()->create(['user_id' => $uVogal->id, 'seat' => FiscalCouncilSeat::VOGAL_1, 'active' => true]);
        $mVogal2 = FiscalCouncilMember::factory()->create(['seat' => FiscalCouncilSeat::VOGAL_2, 'active' => true]);

        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::EM_REVISAO,
        ]);

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::DRAFT,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da CBN-GO',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $uRelator->id,
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        // Autenticado como Relator
        $this->actingAs($uRelator);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionVisible('editOpinionDraft')
            ->assertActionVisible('issueOpinion')
            ->assertActionVisible('commentOpinion');

        // Autenticado como Vogal
        $this->actingAs($uVogal);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionHidden('editOpinionDraft')
            ->assertActionHidden('issueOpinion')
            ->assertActionVisible('commentOpinion');
    }

    public function test_relator_sees_cancel_action_when_opinion_is_issued_and_can_download_pdf(): void
    {
        Storage::fake('local');

        $uRelator = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Abílio Rocha']);
        $mRelator = FiscalCouncilMember::factory()->create(['user_id' => $uRelator->id, 'seat' => FiscalCouncilSeat::RELATOR, 'active' => true]);
        $mVogal1 = FiscalCouncilMember::factory()->create(['seat' => FiscalCouncilSeat::VOGAL_1, 'active' => true]);
        $mVogal2 = FiscalCouncilMember::factory()->create(['seat' => FiscalCouncilSeat::VOGAL_2, 'active' => true]);

        $report = JubanReport::factory()->create([
            'year' => 2026,
            'quarter' => 1,
            'status' => JubanReportStatus::EM_REVISAO,
        ]);

        $pdfPath = 'juban-opinions/test-opinion.pdf';
        Storage::disk('local')->put($pdfPath, '%PDF-dummy');

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::ISSUED,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da CBN-GO',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $uRelator->id,
            'issued_at' => now(),
            'pdf_path' => $pdfPath,
            'pdf_sha256' => hash('sha256', '%PDF-dummy'),
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal1->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        $this->actingAs($uRelator);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionVisible('cancelOpinion')
            ->assertActionVisible('downloadOpinionPdf');
    }
}
