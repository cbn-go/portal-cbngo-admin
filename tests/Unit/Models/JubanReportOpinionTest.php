<?php

namespace Tests\Unit\Models;

use App\Enums\FiscalCouncilSeat;
use App\Enums\JubanReportOpinionMemberRole;
use App\Enums\JubanReportOpinionResult;
use App\Enums\JubanReportOpinionStatus;
use App\Models\FiscalCouncilMember;
use App\Models\JubanReport;
use App\Models\JubanReportOpinion;
use App\Models\JubanReportOpinionMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JubanReportOpinionTest extends TestCase
{
    use RefreshDatabase;

    public function test_opinion_result_and_status_enums_and_relations(): void
    {
        $user = User::factory()->create();
        $report = JubanReport::factory()->create();

        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::DRAFT,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da CBN-GO',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $user->id,
        ]);

        $this->assertTrue($opinion->isDraft());
        $this->assertFalse($opinion->isIssued());
        $this->assertSame($report->id, $opinion->report->id);
        $this->assertSame($user->id, $opinion->draftedBy->id);
    }

    public function test_opinion_members_relations_and_roles(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $m1 = FiscalCouncilMember::factory()->create(['user_id' => $user1->id, 'seat' => FiscalCouncilSeat::RELATOR]);
        $m2 = FiscalCouncilMember::factory()->create(['user_id' => $user2->id, 'seat' => FiscalCouncilSeat::VOGAL_1]);

        $report = JubanReport::factory()->create();
        $opinion = JubanReportOpinion::query()->create([
            'report_id' => $report->id,
            'result' => JubanReportOpinionResult::APROVADO,
            'status' => JubanReportOpinionStatus::DRAFT,
            'meeting_date' => '2026-06-17',
            'meeting_place' => 'sede da CBN-GO',
            'city' => 'Goiânia (GO)',
            'issued_on' => '2026-06-17',
            'drafted_by' => $user1->id,
        ]);

        $relatorMember = JubanReportOpinionMember::query()->create([
            'opinion_id' => $opinion->id,
            'member_id' => $m1->id,
            'role' => JubanReportOpinionMemberRole::RELATOR,
            'position' => 1,
        ]);

        $vogalMember = JubanReportOpinionMember::query()->create([
            'opinion_id' => $opinion->id,
            'member_id' => $m2->id,
            'role' => JubanReportOpinionMemberRole::VOGAL,
            'position' => 2,
        ]);

        $this->assertEquals($relatorMember->id, $opinion->fresh()->relatorMember->id);
        $this->assertCount(1, $opinion->fresh()->vogalMembers);
    }
}
