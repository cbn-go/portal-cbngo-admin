<?php

namespace Tests\Unit\Models;

use App\Enums\NoticePriority;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use PHPUnit\Framework\TestCase;

class EnumTest extends TestCase
{
    public function test_user_role_backed_values(): void
    {
        $this->assertSame('super_admin', UserRole::SUPER_ADMIN->value);
        $this->assertSame('convention_editor', UserRole::CONVENTION_EDITOR->value);
        $this->assertSame('author', UserRole::AUTHOR->value);
        $this->assertSame('church_representative', UserRole::CHURCH_REPRESENTATIVE->value);
    }

    public function test_publish_status_backed_values(): void
    {
        $this->assertSame('draft', PublishStatus::DRAFT->value);
        $this->assertSame('pending_review', PublishStatus::PENDING_REVIEW->value);
        $this->assertSame('published', PublishStatus::PUBLISHED->value);
        $this->assertSame('archived', PublishStatus::ARCHIVED->value);
    }

    public function test_notice_priority_backed_values(): void
    {
        $this->assertSame('normal', NoticePriority::NORMAL->value);
        $this->assertSame('high', NoticePriority::HIGH->value);
        $this->assertSame('urgent', NoticePriority::URGENT->value);
    }
}
