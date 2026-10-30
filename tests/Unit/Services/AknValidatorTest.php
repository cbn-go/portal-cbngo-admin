<?php

namespace Tests\Unit\Services;

use App\Exceptions\AknValidationException;
use App\Services\AknValidator;
use Tests\TestCase;

class AknValidatorTest extends TestCase
{
    private AknValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new AknValidator;
    }

    public function test_validates_canonical_statute_xml_against_xsd(): void
    {
        $canonicalXml = file_get_contents(database_path('seeders/data/estatuto-2025-10-18.akn.xml'));

        $this->assertTrue($this->validator->isValid($canonicalXml));
        $this->assertEmpty($this->validator->getErrors($canonicalXml));

        // validate() should not throw
        $this->validator->validate($canonicalXml);
    }

    public function test_rejects_malformed_xml(): void
    {
        $this->expectException(AknValidationException::class);
        $this->validator->validate('<akomaNtoso><unclosed>');
    }

    public function test_rejects_xml_with_invalid_tags_or_schema_violations(): void
    {
        $invalidXml = '<?xml version="1.0" encoding="UTF-8"?>
<akomaNtoso xmlns="http://docs.oasis-open.org/legaldocml/ns/akn/3.0">
  <act name="estatuto">
    <body>
      <invalidElement eId="inv_1">
        <p>Invalid</p>
      </invalidElement>
    </body>
  </act>
</akomaNtoso>';

        $this->assertFalse($this->validator->isValid($invalidXml));
        $errors = $this->validator->getErrors($invalidXml);
        $this->assertNotEmpty($errors);

        $this->expectException(AknValidationException::class);
        $this->validator->validate($invalidXml);
    }

    public function test_rejects_xml_with_duplicate_eids(): void
    {
        $duplicateEidXml = '<?xml version="1.0" encoding="UTF-8"?>
<akomaNtoso xmlns="http://docs.oasis-open.org/legaldocml/ns/akn/3.0">
  <act name="estatuto">
    <meta>
      <identification source="#cbngo">
        <FRBRWork>
          <FRBRthis value="/akn/br/act/estatuto/cbngo/2025-10-18"/>
          <FRBRuri value="/akn/br/act/estatuto/cbngo/2025-10-18"/>
          <FRBRdate date="2025-10-18" name="approval"/>
          <FRBRauthor href="#cbngo" as="#author"/>
          <FRBRcountry value="br"/>
          <FRBRsubtype value="statute"/>
          <FRBRnumber value="2025"/>
          <FRBRname value="Estatuto"/>
        </FRBRWork>
        <FRBRExpression>
          <FRBRthis value="/akn/br/act/estatuto/cbngo/2025-10-18/por@2025-10-18"/>
          <FRBRuri value="/akn/br/act/estatuto/cbngo/2025-10-18/por@2025-10-18"/>
          <FRBRdate date="2025-10-18" name="approval"/>
          <FRBRauthor href="#cbngo" as="#editor"/>
          <FRBRlanguage language="por"/>
        </FRBRExpression>
        <FRBRManifestation>
          <FRBRthis value="/akn/br/act/estatuto/cbngo/2025-10-18/por@2025-10-18/main.xml"/>
          <FRBRuri value="/akn/br/act/estatuto/cbngo/2025-10-18/por@2025-10-18.akn"/>
          <FRBRdate date="2025-10-18" name="generation"/>
          <FRBRauthor href="#cbngo" as="#publisher"/>
          <FRBRformat value="xml"/>
        </FRBRManifestation>
      </identification>
    </meta>
    <body>
      <chapter eId="chp_1">
        <num>Capítulo I</num>
        <heading>Título</heading>
        <article eId="art_1">
          <num>Art. 1º</num>
          <content><p>Texto</p></content>
        </article>
        <article eId="art_1">
          <num>Art. 2º</num>
          <content><p>Texto</p></content>
        </article>
      </chapter>
    </body>
  </act>
</akomaNtoso>';

        $this->assertFalse($this->validator->isValid($duplicateEidXml));
        $errors = $this->validator->getErrors($duplicateEidXml);
        $this->assertTrue(collect($errors)->contains(fn ($err) => str_contains($err, 'art_1')));

        $this->expectException(AknValidationException::class);
        $this->expectExceptionMessageMatches('/art_1/');
        $this->validator->validate($duplicateEidXml);
    }

    public function test_rejects_xml_with_invalid_root_or_namespace(): void
    {
        $wrongNamespaceXml = '<?xml version="1.0" encoding="UTF-8"?>
<akomaNtoso xmlns="http://wrong.namespace/akn/3.0">
  <act name="estatuto">
    <body><p>wrong</p></body>
  </act>
</akomaNtoso>';

        $this->assertFalse($this->validator->isValid($wrongNamespaceXml));

        $this->expectException(AknValidationException::class);
        $this->validator->validate($wrongNamespaceXml);
    }
}
