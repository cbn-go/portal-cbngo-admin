<?php

namespace Tests\Unit\Services;

use App\DTOs\Statute\StatuteStructure;
use App\Services\AknStatuteParser;
use Tests\TestCase;

class AknStatuteParserTest extends TestCase
{
    private AknStatuteParser $parser;

    private string $xml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new AknStatuteParser;
        $this->xml = file_get_contents(database_path('seeders/data/estatuto-2025-10-18.akn.xml'));
    }

    public function test_parses_canonical_statute_structure(): void
    {
        $structure = $this->parser->parse($this->xml);

        $this->assertInstanceOf(StatuteStructure::class, $structure);
        $this->assertStringContainsString('ESTATUTO DA CONVENÇÃO BATISTA NACIONAL', $structure->title);
        $this->assertStringContainsString('Anápolis, 18 de outubro de 2025.', $structure->conclusions);
        $this->assertSame('/akn/br/act/estatuto/cbngo/2025-10-18', $structure->frbrWorkUri);

        $this->assertCount(6, $structure->chapters);
    }

    public function test_counts_chapters_sections_and_articles_accurately(): void
    {
        $this->assertSame(6, $this->parser->countChapters($this->xml));
        $this->assertSame(5, $this->parser->countSections($this->xml));
        $this->assertSame(21, $this->parser->countArticles($this->xml));
    }

    public function test_chapter_and_section_hierarchy(): void
    {
        $structure = $this->parser->parse($this->xml);

        // Chapter 1
        $chp1 = $structure->chapters[0];
        $this->assertSame('chp_1', $chp1->eId);
        $this->assertSame('Do Nome, da Constituição e da Sede', $chp1->heading);
        $this->assertCount(2, $chp1->articles);
        $this->assertCount(0, $chp1->sections);

        // Chapter 3
        $chp3 = $structure->chapters[2];
        $this->assertSame('chp_3', $chp3->eId);
        $this->assertSame('Dos Órgãos Deliberativos e Administrativos', $chp3->heading);
        $this->assertCount(1, $chp3->articles); // Art. 5
        $this->assertCount(5, $chp3->sections);

        $sec1 = $chp3->sections[0];
        $this->assertSame('chp_3__sec_1', $sec1->eId);
        $this->assertSame('Da Assembleia Geral – AG', $sec1->heading);
        $this->assertCount(1, $sec1->articles); // Art. 6

        $sec2 = $chp3->sections[1];
        $this->assertSame('chp_3__sec_2', $sec2->eId);
        $this->assertSame('Da Diretoria', $sec2->heading);
        $this->assertCount(2, $sec2->articles); // Art. 7, Art. 8
    }

    public function test_find_article_by_eid(): void
    {
        $art8 = $this->parser->findArticleByEId($this->xml, 'art_8');
        $this->assertNotNull($art8);
        $this->assertSame('Art. 8º', $art8->num);
        $this->assertStringContainsString('Competem aos membros da Diretoria', $art8->introText);
        $this->assertNotEmpty($art8->elements);

        $art12 = $this->parser->findArticleByEId($this->xml, 'art_12');
        $this->assertNotNull($art12);
        $this->assertSame('Art. 12', $art12->num);
        $this->assertStringContainsString('Patrimônio da CBN-GO', $art12->introText);

        $artNotFound = $this->parser->findArticleByEId($this->xml, 'art_99');
        $this->assertNull($artNotFound);
    }

    public function test_returns_unique_eids_list(): void
    {
        $eids = $this->parser->getUniqueEIds($this->xml);
        $this->assertCount(95, $eids);
        $this->assertContains('chp_1', $eids);
        $this->assertContains('chp_3__sec_2', $eids);
        $this->assertContains('art_8', $eids);
        $this->assertContains('art_8__point_I__point_a', $eids);
        $this->assertContains('art_12__para_4_a', $eids);
    }
}
