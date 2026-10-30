<?php

namespace Tests\Unit\Services;

use App\Services\AknStatuteSerializer;
use App\Services\StatuteDiffService;
use Tests\TestCase;

class StatuteDiffServiceTest extends TestCase
{
    private StatuteDiffService $diffService;

    private AknStatuteSerializer $serializer;

    private string $canonicalXml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->diffService = new StatuteDiffService;
        $this->serializer = new AknStatuteSerializer;
        $this->canonicalXml = file_get_contents(database_path('seeders/data/estatuto-2025-10-18.akn.xml'));
    }

    public function test_identical_xmls_have_zero_modifications(): void
    {
        $diff = $this->diffService->compare($this->canonicalXml, $this->canonicalXml);

        $this->assertSame(0, $diff->addedCount);
        $this->assertSame(0, $diff->modifiedCount);
        $this->assertSame(0, $diff->removedCount);
        $this->assertSame(21, $diff->unchangedCount);
        $this->assertFalse($diff->hasDifferences());
    }

    public function test_detects_modified_article_caput(): void
    {
        $formData = $this->serializer->toFormData($this->canonicalXml);
        $formData['chapters'][0]['articles'][0]['intro'] = 'Novo texto alterado do caput.';
        $modifiedXml = $this->serializer->toXml($formData, [
            'title' => 'Estatuto',
            'version_label' => '2025-v2',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
        ]);

        $diff = $this->diffService->compare($this->canonicalXml, $modifiedXml);

        $this->assertTrue($diff->hasDifferences());
        $this->assertSame(1, $diff->modifiedCount);
        $this->assertSame(0, $diff->addedCount);
        $this->assertSame(0, $diff->removedCount);
        $this->assertSame(20, $diff->unchangedCount);

        $modifiedArticle = collect($diff->articles)->firstWhere('type', 'modified');
        $this->assertNotNull($modifiedArticle);
        $this->assertSame('Art. 1º', $modifiedArticle->num);
    }

    public function test_detects_added_article(): void
    {
        $formData = $this->serializer->toFormData($this->canonicalXml);
        $formData['chapters'][0]['articles'][] = [
            'eId' => null,
            'num' => 'Art. 22',
            'heading' => null,
            'intro' => 'Novo artigo 22 para teste de inclusão.',
            'paragraphs' => [],
            'points' => [],
        ];

        $modifiedXml = $this->serializer->toXml($formData, [
            'title' => 'Estatuto',
            'version_label' => '2025-v2',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
        ]);

        $diff = $this->diffService->compare($this->canonicalXml, $modifiedXml);

        $this->assertTrue($diff->hasDifferences());
        $this->assertSame(1, $diff->addedCount);
        $this->assertSame(0, $diff->modifiedCount);
        $this->assertSame(0, $diff->removedCount);
    }
}
