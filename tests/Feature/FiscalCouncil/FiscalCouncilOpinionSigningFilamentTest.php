<?php

namespace Tests\Feature\FiscalCouncil;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Enums\JubanReportStatus;
use App\Enums\JubanSigningRole;
use App\Enums\UserRole;
use App\Filament\FiscalCouncil\Resources\JubanReportResource\Pages\ViewJubanReport;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportOpinionMember;
use App\Models\JubanReportSignature;
use App\Models\Passkey;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class FiscalCouncilOpinionSigningFilamentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('conselho-fiscal'));
        Storage::fake('local');
    }

    public function test_sign_opinion_action_visible_only_to_pending_meeting_members(): void
    {
        $uRelator = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Abílio Rocha', 'is_active' => true]);
        $uVogal1 = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Juarez Araújo', 'is_active' => true]);
        $uVogal2 = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Marilane Jacintho', 'is_active' => true]);
        $uOther = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Conselheiro de Fora', 'is_active' => true]);

        $mRelator = FiscalCouncilMember::factory()->create(['user_id' => $uRelator->id, 'seat' => FiscalCouncilSeat::RELATOR, 'active' => true]);
        $mVogal1 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal1->id, 'seat' => FiscalCouncilSeat::VOGAL_1, 'active' => true]);
        $mVogal2 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal2->id, 'seat' => FiscalCouncilSeat::VOGAL_2, 'active' => true]);
        $mOther = FiscalCouncilMember::factory()->create(['user_id' => $uOther->id, 'seat' => FiscalCouncilSeat::SUPLENTE_2, 'active' => true]);

        $reportPath = 'juban-reports/report.pdf';
        Storage::disk('local')->put($reportPath, '%PDF-report');
        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::EM_REVISAO,
            'pdf_path' => $reportPath,
            'pdf_sha256' => hash('sha256', '%PDF-report'),
        ]);

        $opinionPath = 'juban-opinions/opinion.pdf';
        Storage::disk('local')->put($opinionPath, '%PDF-opinion');
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
            'pdf_path' => $opinionPath,
            'pdf_sha256' => hash('sha256', '%PDF-opinion'),
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal1->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        // 1. Relator vê signOpinion
        $this->actingAs($uRelator);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionVisible('signOpinion');

        // 2. Vogal 1 vê signOpinion
        $this->actingAs($uVogal1);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionVisible('signOpinion');

        // 3. Outro membro de fora não vê signOpinion
        $this->actingAs($uOther);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionHidden('signOpinion');
    }

    public function test_download_signed_opinion_pdf_visible_when_quorum_complete(): void
    {
        $uRelator = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Abílio Rocha', 'is_active' => true]);
        $uVogal1 = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Juarez Araújo', 'is_active' => true]);
        $uVogal2 = User::factory()->create(['role' => UserRole::FISCAL_COUNCIL, 'name' => 'Marilane Jacintho', 'is_active' => true]);

        $mRelator = FiscalCouncilMember::factory()->create(['user_id' => $uRelator->id, 'seat' => FiscalCouncilSeat::RELATOR, 'active' => true]);
        $mVogal1 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal1->id, 'seat' => FiscalCouncilSeat::VOGAL_1, 'active' => true]);
        $mVogal2 = FiscalCouncilMember::factory()->create(['user_id' => $uVogal2->id, 'seat' => FiscalCouncilSeat::VOGAL_2, 'active' => true]);

        $report = JubanReport::factory()->create([
            'status' => JubanReportStatus::APROVADO,
            'pdf_path' => 'juban-reports/report.pdf',
            'pdf_sha256' => hash('sha256', 'dummy'),
        ]);

        $signedPath = 'juban-opinions/opinion-signed.pdf';
        Storage::disk('local')->put($signedPath, '%PDF-signed');

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
            'pdf_path' => 'juban-opinions/opinion.pdf',
            'pdf_sha256' => hash('sha256', 'dummy'),
            'signed_pdf_path' => $signedPath,
            'signed_pdf_sha256' => hash('sha256', '%PDF-signed'),
        ]);

        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mRelator->id, 'role' => JubanReportOpinionMemberRole::RELATOR, 'position' => 1]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal1->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 2]);
        JubanReportOpinionMember::query()->create(['opinion_id' => $opinion->id, 'member_id' => $mVogal2->id, 'role' => JubanReportOpinionMemberRole::VOGAL, 'position' => 3]);

        $pRelator = Passkey::factory()->create(['user_id' => $uRelator->id]);
        $pVogal1 = Passkey::factory()->create(['user_id' => $uVogal1->id]);
        $pVogal2 = Passkey::factory()->create(['user_id' => $uVogal2->id]);

        JubanReportSignature::query()->create(['report_id' => $report->id, 'opinion_id' => $opinion->id, 'user_id' => $uRelator->id, 'role' => JubanSigningRole::CONSELHO_RELATOR, 'passkey_id' => $pRelator->id, 'nonce' => 'n1', 'challenge' => 'c1', 'client_data_json' => '{}', 'authenticator_data' => 'a1', 'signature' => 's1', 'pdf_sha256' => 'h1', 'signed_at' => now()]);
        JubanReportSignature::query()->create(['report_id' => $report->id, 'opinion_id' => $opinion->id, 'user_id' => $uVogal1->id, 'role' => JubanSigningRole::CONSELHO_VOGAL_1, 'passkey_id' => $pVogal1->id, 'nonce' => 'n2', 'challenge' => 'c2', 'client_data_json' => '{}', 'authenticator_data' => 'a2', 'signature' => 's2', 'pdf_sha256' => 'h2', 'signed_at' => now()]);
        JubanReportSignature::query()->create(['report_id' => $report->id, 'opinion_id' => $opinion->id, 'user_id' => $uVogal2->id, 'role' => JubanSigningRole::CONSELHO_VOGAL_2, 'passkey_id' => $pVogal2->id, 'nonce' => 'n3', 'challenge' => 'c3', 'client_data_json' => '{}', 'authenticator_data' => 'a3', 'signature' => 's3', 'pdf_sha256' => 'h3', 'signed_at' => now()]);

        $this->actingAs($uRelator);
        Livewire::test(ViewJubanReport::class, ['record' => $report->id])
            ->assertActionVisible('downloadSignedOpinionPdf');
    }
}
