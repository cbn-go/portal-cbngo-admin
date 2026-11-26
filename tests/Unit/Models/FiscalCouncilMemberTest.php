<?php

namespace Tests\Unit\Models;

use App\Enums\FiscalCouncilSeat;
use App\Models\FiscalCouncilMember;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class FiscalCouncilMemberTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_fiscal_council_member_with_triennium_and_qualifications(): void
    {
        $user = User::factory()->create();

        $member = FiscalCouncilMember::query()->create([
            'user_id' => $user->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'title' => 'Pr.',
            'qualification' => 'Técnico em contabilidade',
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        $this->assertDatabaseHas('fiscal_council_members', [
            'id' => $member->id,
            'user_id' => $user->id,
            'seat' => 'relator',
            'title' => 'Pr.',
            'qualification' => 'Técnico em contabilidade',
            'active' => 1,
        ]);

        $this->assertSame(FiscalCouncilSeat::RELATOR, $member->seat);
        $this->assertTrue($member->seat->isEfetivo());
        $this->assertFalse($member->seat->isSuplente());
    }

    public function test_suplente_seats_are_identified_correctly(): void
    {
        $this->assertTrue(FiscalCouncilSeat::SUPLENTE_1->isSuplente());
        $this->assertTrue(FiscalCouncilSeat::SUPLENTE_2->isSuplente());
        $this->assertTrue(FiscalCouncilSeat::SUPLENTE_3->isSuplente());
        $this->assertFalse(FiscalCouncilSeat::SUPLENTE_1->isEfetivo());
    }

    public function test_term_is_active_when_within_dates_and_marked_active(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $member = FiscalCouncilMember::query()->create([
            'user_id' => $user->id,
            'seat' => FiscalCouncilSeat::VOGAL_1,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        $this->assertTrue($member->isTermActive(Carbon::parse('2026-06-15')));
        $this->assertTrue($member->canSign(Carbon::parse('2026-06-15')));

        // Antes do início do mandato
        $this->assertFalse($member->isTermActive(Carbon::parse('2025-12-31')));
        $this->assertFalse($member->canSign(Carbon::parse('2025-12-31')));

        // Após o encerramento do mandato (vencido)
        $this->assertFalse($member->isTermActive(Carbon::parse('2029-01-01')));
        $this->assertFalse($member->canSign(Carbon::parse('2029-01-01')));
    }

    public function test_cannot_sign_when_member_is_inactive_or_user_is_inactive(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $member = FiscalCouncilMember::query()->create([
            'user_id' => $user->id,
            'seat' => FiscalCouncilSeat::VOGAL_2,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => false,
        ]);

        $this->assertFalse($member->isTermActive(Carbon::parse('2026-06-15')));
        $this->assertFalse($member->canSign(Carbon::parse('2026-06-15')));

        // Se membro ativo mas usuário inativo
        $inactiveUser = User::factory()->create(['is_active' => false]);
        $memberActive = FiscalCouncilMember::query()->create([
            'user_id' => $inactiveUser->id,
            'seat' => FiscalCouncilSeat::SUPLENTE_1,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        $this->assertTrue($memberActive->isTermActive(Carbon::parse('2026-06-15')));
        $this->assertFalse($memberActive->canSign(Carbon::parse('2026-06-15')));
    }

    public function test_cannot_have_multiple_active_members_on_same_seat(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        FiscalCouncilMember::query()->create([
            'user_id' => $user1->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Já existe um membro ativo ocupando o assento');

        FiscalCouncilMember::query()->create([
            'user_id' => $user2->id,
            'seat' => FiscalCouncilSeat::RELATOR,
            'term_starts_on' => '2026-01-01',
            'term_ends_on' => '2028-12-31',
            'active' => true,
        ]);
    }
}
