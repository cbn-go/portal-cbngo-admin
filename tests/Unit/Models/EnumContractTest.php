<?php

namespace Tests\Unit\Models;

use App\Enums\NoticePriority;
use App\Enums\PublishStatus;
use App\Enums\UserRole;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use PHPUnit\Framework\TestCase;

class EnumContractTest extends TestCase
{
    public function test_user_role_implements_filament_contracts(): void
    {
        $this->assertInstanceOf(HasLabel::class, UserRole::SUPER_ADMIN);
        $this->assertInstanceOf(HasColor::class, UserRole::SUPER_ADMIN);

        $this->assertSame('Super Administrador', UserRole::SUPER_ADMIN->getLabel());
        $this->assertSame('Editor da Convenção', UserRole::CONVENTION_EDITOR->getLabel());
        $this->assertSame('Autor', UserRole::AUTHOR->getLabel());
        $this->assertSame('Representante de Igreja', UserRole::CHURCH_REPRESENTATIVE->getLabel());

        $this->assertSame('danger', UserRole::SUPER_ADMIN->getColor());
        $this->assertSame('warning', UserRole::CONVENTION_EDITOR->getColor());
        $this->assertSame('info', UserRole::AUTHOR->getColor());
        $this->assertSame('success', UserRole::CHURCH_REPRESENTATIVE->getColor());
    }

    public function test_user_role_helper_methods(): void
    {
        $superAdmin = UserRole::SUPER_ADMIN;
        $editor = UserRole::CONVENTION_EDITOR;
        $author = UserRole::AUTHOR;
        $churchRep = UserRole::CHURCH_REPRESENTATIVE;

        $this->assertTrue($superAdmin->isSuperAdmin());
        $this->assertFalse($superAdmin->isConventionEditor());
        $this->assertTrue($superAdmin->hasAdminPanelAccess());
        $this->assertTrue($superAdmin->hasPortalPanelAccess());

        $this->assertTrue($editor->isConventionEditor());
        $this->assertTrue($editor->hasAdminPanelAccess());
        $this->assertFalse($editor->hasPortalPanelAccess());

        $this->assertTrue($author->isAuthor());
        $this->assertFalse($author->hasAdminPanelAccess());
        $this->assertTrue($author->hasPortalPanelAccess());

        $this->assertTrue($churchRep->isChurchRepresentative());
        $this->assertFalse($churchRep->hasAdminPanelAccess());
        $this->assertTrue($churchRep->hasPortalPanelAccess());
    }

    public function test_publish_status_implements_filament_contracts(): void
    {
        $this->assertInstanceOf(HasLabel::class, PublishStatus::DRAFT);
        $this->assertInstanceOf(HasColor::class, PublishStatus::DRAFT);
        $this->assertInstanceOf(HasIcon::class, PublishStatus::DRAFT);

        $this->assertSame('Rascunho', PublishStatus::DRAFT->getLabel());
        $this->assertSame('Pendente de Revisão', PublishStatus::PENDING_REVIEW->getLabel());
        $this->assertSame('Publicado', PublishStatus::PUBLISHED->getLabel());
        $this->assertSame('Arquivado', PublishStatus::ARCHIVED->getLabel());

        $this->assertSame('gray', PublishStatus::DRAFT->getColor());
        $this->assertSame('warning', PublishStatus::PENDING_REVIEW->getColor());
        $this->assertSame('success', PublishStatus::PUBLISHED->getColor());
        $this->assertSame('danger', PublishStatus::ARCHIVED->getColor());

        $this->assertSame('heroicon-m-pencil-square', PublishStatus::DRAFT->getIcon());
        $this->assertSame('heroicon-m-clock', PublishStatus::PENDING_REVIEW->getIcon());
        $this->assertSame('heroicon-m-check-circle', PublishStatus::PUBLISHED->getIcon());
        $this->assertSame('heroicon-m-archive-box', PublishStatus::ARCHIVED->getIcon());
    }

    public function test_notice_priority_implements_filament_contracts(): void
    {
        $this->assertInstanceOf(HasLabel::class, NoticePriority::NORMAL);
        $this->assertInstanceOf(HasColor::class, NoticePriority::NORMAL);
        $this->assertInstanceOf(HasIcon::class, NoticePriority::NORMAL);

        $this->assertSame('1: Normal', NoticePriority::NORMAL->getLabel());
        $this->assertSame('2: Alta', NoticePriority::HIGH->getLabel());
        $this->assertSame('3: Urgente', NoticePriority::URGENT->getLabel());

        $this->assertSame('gray', NoticePriority::NORMAL->getColor());
        $this->assertSame('warning', NoticePriority::HIGH->getColor());
        $this->assertSame('danger', NoticePriority::URGENT->getColor());

        $this->assertSame('heroicon-m-information-circle', NoticePriority::NORMAL->getIcon());
        $this->assertSame('heroicon-m-exclamation-circle', NoticePriority::HIGH->getIcon());
        $this->assertSame('heroicon-m-exclamation-triangle', NoticePriority::URGENT->getIcon());
    }
}
