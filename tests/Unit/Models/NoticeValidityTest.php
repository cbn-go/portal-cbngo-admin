<?php

namespace Tests\Unit\Models;

use App\Enums\NoticeValidityStatus;
use App\Models\Notice;
use App\Models\User;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class NoticeValidityTest extends TestCase
{
    use RefreshDatabase;

    public function test_notice_validity_status_enum_contracts_and_values(): void
    {
        $this->assertTrue(is_subclass_of(NoticeValidityStatus::class, HasLabel::class));
        $this->assertTrue(is_subclass_of(NoticeValidityStatus::class, HasColor::class));
        $this->assertTrue(is_subclass_of(NoticeValidityStatus::class, HasIcon::class));

        $this->assertSame('active', NoticeValidityStatus::ACTIVE->value);
        $this->assertSame('scheduled', NoticeValidityStatus::SCHEDULED->value);
        $this->assertSame('expired', NoticeValidityStatus::EXPIRED->value);
        $this->assertSame('inactive', NoticeValidityStatus::INACTIVE->value);

        $this->assertSame('Vigente', NoticeValidityStatus::ACTIVE->getLabel());
        $this->assertSame('Agendado', NoticeValidityStatus::SCHEDULED->getLabel());
        $this->assertSame('Expirado', NoticeValidityStatus::EXPIRED->getLabel());
        $this->assertSame('Inativo', NoticeValidityStatus::INACTIVE->getLabel());

        $this->assertSame('success', NoticeValidityStatus::ACTIVE->getColor());
        $this->assertSame('info', NoticeValidityStatus::SCHEDULED->getColor());
        $this->assertSame('danger', NoticeValidityStatus::EXPIRED->getColor());
        $this->assertSame('gray', NoticeValidityStatus::INACTIVE->getColor());

        $this->assertSame('heroicon-m-check-circle', NoticeValidityStatus::ACTIVE->getIcon());
        $this->assertSame('heroicon-m-clock', NoticeValidityStatus::SCHEDULED->getIcon());
        $this->assertSame('heroicon-m-x-circle', NoticeValidityStatus::EXPIRED->getIcon());
        $this->assertSame('heroicon-m-no-symbol', NoticeValidityStatus::INACTIVE->getIcon());
    }

    public function test_notice_validity_status_accessor_inactive(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => false,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $this->assertSame(NoticeValidityStatus::INACTIVE, $notice->validity_status);
        $this->assertFalse($notice->isCurrentlyActive());
    }

    public function test_notice_validity_status_accessor_active_without_dates(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $this->assertSame(NoticeValidityStatus::ACTIVE, $notice->validity_status);
        $this->assertTrue($notice->isCurrentlyActive());
    }

    public function test_notice_validity_status_accessor_scheduled(): void
    {
        Carbon::setTestNow('2026-10-08 12:00:00');

        $user = User::factory()->create();
        $notice = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'starts_at' => Carbon::parse('2026-10-08 13:00:00'),
            'expires_at' => Carbon::parse('2026-10-08 18:00:00'),
        ]);

        $this->assertSame(NoticeValidityStatus::SCHEDULED, $notice->validity_status);
        $this->assertFalse($notice->isCurrentlyActive());

        Carbon::setTestNow();
    }

    public function test_notice_validity_status_accessor_expired(): void
    {
        Carbon::setTestNow('2026-10-08 12:00:00');

        $user = User::factory()->create();
        $notice = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'starts_at' => Carbon::parse('2026-10-08 08:00:00'),
            'expires_at' => Carbon::parse('2026-10-08 11:00:00'),
        ]);

        $this->assertSame(NoticeValidityStatus::EXPIRED, $notice->validity_status);
        $this->assertFalse($notice->isCurrentlyActive());

        Carbon::setTestNow();
    }

    public function test_notice_validity_status_accessor_currently_active_within_bounds(): void
    {
        Carbon::setTestNow('2026-10-08 12:00:00');

        $user = User::factory()->create();
        $notice = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'starts_at' => Carbon::parse('2026-10-08 10:00:00'),
            'expires_at' => Carbon::parse('2026-10-08 15:00:00'),
        ]);

        $this->assertSame(NoticeValidityStatus::ACTIVE, $notice->validity_status);
        $this->assertTrue($notice->isCurrentlyActive());

        Carbon::setTestNow();
    }

    public function test_notice_validity_scopes(): void
    {
        Carbon::setTestNow('2026-10-08 12:00:00');
        $user = User::factory()->create();

        // 1. Ativo sem datas
        $activeNoDates = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        // 2. Ativo no intervalo
        $activeInBounds = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'starts_at' => Carbon::parse('2026-10-08 10:00:00'),
            'expires_at' => Carbon::parse('2026-10-08 14:00:00'),
        ]);

        // 3. Agendado futuro
        $scheduled = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'starts_at' => Carbon::parse('2026-10-08 15:00:00'),
            'expires_at' => Carbon::parse('2026-10-08 20:00:00'),
        ]);

        // 4. Expirado
        $expired = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => true,
            'starts_at' => Carbon::parse('2026-10-08 08:00:00'),
            'expires_at' => Carbon::parse('2026-10-08 10:00:00'),
        ]);

        // 5. Inativo dentro de datas
        $inactive = Notice::factory()->create([
            'user_id' => $user->id,
            'is_active' => false,
            'starts_at' => Carbon::parse('2026-10-08 10:00:00'),
            'expires_at' => Carbon::parse('2026-10-08 14:00:00'),
        ]);

        $currentlyActiveIds = Notice::query()->currentlyActive()->pluck('id')->all();
        $this->assertContains($activeNoDates->id, $currentlyActiveIds);
        $this->assertContains($activeInBounds->id, $currentlyActiveIds);
        $this->assertNotContains($scheduled->id, $currentlyActiveIds);
        $this->assertNotContains($expired->id, $currentlyActiveIds);
        $this->assertNotContains($inactive->id, $currentlyActiveIds);

        $scheduledIds = Notice::query()->scheduled()->pluck('id')->all();
        $this->assertSame([$scheduled->id], $scheduledIds);

        $expiredIds = Notice::query()->expired()->pluck('id')->all();
        $this->assertSame([$expired->id], $expiredIds);

        Carbon::setTestNow();
    }
}
