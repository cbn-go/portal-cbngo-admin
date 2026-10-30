<?php

namespace Tests\Unit\Services;

use App\Services\AknStatuteSerializer;
use App\Services\AknValidator;
use Tests\TestCase;

class AknStatuteSerializerTest extends TestCase
{
    private AknStatuteSerializer $serializer;

    private AknValidator $validator;

    private string $canonicalXml;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serializer = new AknStatuteSerializer;
        $this->validator = new AknValidator;
        $this->canonicalXml = file_get_contents(database_path('seeders/data/estatuto-2025-10-18.akn.xml'));
    }

    public function test_converts_canonical_xml_to_form_data(): void
    {
        $formData = $this->serializer->toFormData($this->canonicalXml);

        $this->assertArrayHasKey('chapters', $formData);
        $this->assertCount(6, $formData['chapters']);

        // Capítulo I
        $chp1 = $formData['chapters'][0];
        $this->assertSame('chp_1', $chp1['eId']);
        $this->assertSame('CAPÍTULO I', $chp1['num']);
        $this->assertSame('Do Nome, da Constituição e da Sede', $chp1['heading']);
        $this->assertCount(2, $chp1['articles']);

        // Capítulo III tem 1 artigo e 5 seções
        $chp3 = $formData['chapters'][2];
        $this->assertSame('chp_3', $chp3['eId']);
        $this->assertCount(1, $chp3['articles']);
        $this->assertCount(5, $chp3['sections']);

        // Total de artigos somados deve ser 21
        $totalArticles = 0;
        foreach ($formData['chapters'] as $chp) {
            $totalArticles += count($chp['articles'] ?? []);
            foreach ($chp['sections'] ?? [] as $sec) {
                $totalArticles += count($sec['articles'] ?? []);
            }
        }
        $this->assertSame(21, $totalArticles);
    }

    public function test_converts_form_data_back_to_valid_xml(): void
    {
        $metadata = [
            'title' => 'Estatuto da Convenção Batista Nacional do Estado de Goiás',
            'version_label' => '2025-10-18',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
        ];

        $formData = $this->serializer->toFormData($this->canonicalXml);
        $generatedXml = $this->serializer->toXml($formData, $metadata);

        $this->assertNotEmpty($generatedXml);
        $this->assertTrue($this->validator->isValid($generatedXml), 'Generated XML is not valid against XSD: '.implode('; ', $this->validator->getErrors($generatedXml)));
    }

    public function test_modifying_article_caput_preserves_validity_and_eids(): void
    {
        $metadata = [
            'title' => 'Estatuto da Convenção Batista Nacional do Estado de Goiás',
            'version_label' => '2025-10-18',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
        ];

        $formData = $this->serializer->toFormData($this->canonicalXml);

        // Altera apenas o caput do Art. 1º
        $formData['chapters'][0]['articles'][0]['intro'] = 'Texto modificado do Artigo 1º para teste de edição estruturada.';

        $generatedXml = $this->serializer->toXml($formData, $metadata);

        $this->assertTrue($this->validator->isValid($generatedXml));
        $this->assertStringContainsString('Texto modificado do Artigo 1º', $generatedXml);
        $this->assertStringContainsString('eId="art_1"', $generatedXml);
    }

    public function test_adding_new_article_generates_valid_xml_and_eids(): void
    {
        $metadata = [
            'title' => 'Estatuto da Convenção Batista Nacional do Estado de Goiás',
            'version_label' => '2025-10-18',
            'approved_at' => '2025-10-18',
            'approved_place' => 'Anápolis, GO',
        ];

        $formData = $this->serializer->toFormData($this->canonicalXml);

        // Adiciona um novo artigo no Capítulo I
        $formData['chapters'][0]['articles'][] = [
            'eId' => null, // será gerado automaticamente
            'num' => 'Art. 2º-A',
            'heading' => null,
            'intro' => 'Novo artigo adicionado via editor estruturado.',
            'paragraphs' => [],
            'points' => [],
        ];

        $generatedXml = $this->serializer->toXml($formData, $metadata);

        $this->assertTrue($this->validator->isValid($generatedXml), 'Adding article resulted in invalid XML: '.implode('; ', $this->validator->getErrors($generatedXml)));
        $this->assertStringContainsString('Novo artigo adicionado via editor', $generatedXml);
    }
}
