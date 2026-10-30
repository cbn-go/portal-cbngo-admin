<?php

namespace Tests\Unit\Models;

use App\Enums\AmigoRegion;
use App\Enums\ArticleCategory;
use App\Enums\BoardGroup;
use App\Enums\ClergyTitle;
use App\Enums\DocumentCategory;
use App\Enums\NewsCategory;
use App\Enums\NoticePriority;
use App\Enums\ProjectCategory;
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
            ['presidencia', 'vice_presidencia', 'secretaria', 'tesouraria', 'conplex', 'conselho_fiscal', 'coordenacao_regional'],
            array_column(BoardGroup::cases(), 'value'),
        );
    }

    public function test_amigo_regions_are_recognized_in_legacy_text(): void
    {
        $this->assertSame(['leste', 'oeste', 'norte', 'sudoeste'], array_column(AmigoRegion::cases(), 'value'));
        $this->assertSame('AMIGO Sudoeste', AmigoRegion::SUDOESTE->getLabel());

        $this->assertSame(AmigoRegion::SUDOESTE, AmigoRegion::fromText('Região Sudoeste (Rio Verde e Jataí)'));
        $this->assertSame(AmigoRegion::OESTE, AmigoRegion::fromText('AMIGO Oeste'));
        $this->assertSame(AmigoRegion::LESTE, AmigoRegion::fromText('leste'));
        $this->assertNull(AmigoRegion::fromText('Região Metropolitana de Goiânia'));
        $this->assertNull(AmigoRegion::fromText(null));
    }

    public function test_clergy_title_is_split_from_the_name(): void
    {
        $this->assertSame([ClergyTitle::PASTOR, 'André Luiz Rodrigues da Silva'], ClergyTitle::splitName('Pr. André Luiz Rodrigues da Silva'));
        $this->assertSame([ClergyTitle::PASTORA, 'Helena Albuquerque'], ClergyTitle::splitName('Pra. Helena Albuquerque'));
        $this->assertSame([ClergyTitle::EVANGELISTA, 'Fábio Oliveira Nascimento'], ClergyTitle::splitName(' Ev.  Fábio Oliveira Nascimento '));
        $this->assertSame([ClergyTitle::DIACONO, 'Márcio Antunes'], ClergyTitle::splitName('Diac. Márcio Antunes'));
        $this->assertSame([null, 'Abílio Rocha Neto'], ClergyTitle::splitName('Abílio Rocha Neto'));
        $this->assertSame([null, 'Prado Lima'], ClergyTitle::splitName('Prado Lima'));
    }

    public function test_document_category_backed_values(): void
    {
        $this->assertSame(
            ['estatuto', 'regimento', 'pacto', 'atas', 'outros'],
            array_column(DocumentCategory::cases(), 'value'),
        );
    }

    public function test_project_category_values_and_lausanne_references(): void
    {
        $this->assertSame(['Social', 'Cultural', 'Educação', 'Liderança'], array_column(ProjectCategory::cases(), 'value'));

        $this->assertStringContainsString('art. 5', ProjectCategory::SOCIAL->lausanneReference());
        $this->assertStringContainsString('art. 10', ProjectCategory::CULTURAL->lausanneReference());
        $this->assertStringContainsString('art. 11', ProjectCategory::EDUCACAO->lausanneReference());
        $this->assertStringContainsString('art. 11', ProjectCategory::LIDERANCA->lausanneReference());

        foreach (ProjectCategory::cases() as $category) {
            $this->assertSame($category->value, $category->getLabel());
            $this->assertNotSame('', $category->description());
            $this->assertNotNull($category->getColor());
            $this->assertNotNull($category->getIcon());
        }
    }
}
