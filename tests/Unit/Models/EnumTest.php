<?php

namespace Tests\Unit\Models;

use App\Enums\ArticleCategory;
use App\Enums\BannerFocalPoint;
use App\Enums\BoardGroup;
use App\Enums\DocumentCategory;
use App\Enums\NewsCategory;
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

    public function test_news_category_values_are_public_labels(): void
    {
        $this->assertSame(
            ['Geral', 'Comunicado Oficial', 'Missões', 'Educação Teológica', 'Eventos', 'Ação Social'],
            array_column(NewsCategory::cases(), 'value'),
        );
    }

    public function test_article_category_values_are_public_labels(): void
    {
        $this->assertSame(
            ['Teologia', 'Missões', 'Liderança', 'Família', 'Discipulado', 'Devocional'],
            array_column(ArticleCategory::cases(), 'value'),
        );
    }

    public function test_notice_priority_maps_to_public_banner_type(): void
    {
        $this->assertSame('info', NoticePriority::NORMAL->publicBannerType());
        $this->assertSame('warning', NoticePriority::HIGH->publicBannerType());
        $this->assertSame('critical', NoticePriority::URGENT->publicBannerType());
    }

    public function test_board_group_values_match_public_portal_order(): void
    {
        $this->assertSame(
            ['presidencia', 'vice_presidencia', 'secretaria', 'tesouraria', 'conselho_fiscal', 'coordenacao_regional'],
            array_column(BoardGroup::cases(), 'value'),
        );
    }

    public function test_document_category_backed_values(): void
    {
        $this->assertSame(
            ['estatuto', 'regimento', 'pacto', 'atas', 'outros'],
            array_column(DocumentCategory::cases(), 'value'),
        );
    }

    public function test_banner_focal_point_backed_values(): void
    {
        $this->assertSame(['center', 'left', 'right'], array_column(BannerFocalPoint::cases(), 'value'));
        $this->assertSame('Centro', BannerFocalPoint::CENTER->getLabel());
    }
}
