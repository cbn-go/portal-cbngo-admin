<?php

namespace Tests\Unit\Support;

use App\Support\LinkTarget;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LinkTargetTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: bool, 2: bool}>
     */
    public static function links(): array
    {
        // [url, válido, externo]
        return [
            'site path' => ['/eventos/assembleia', true, false],
            'site root' => ['/', true, false],
            'new domain' => ['https://cbngo.org.br/noticias', true, false],
            'old domain' => ['https://cbngo.com.br/edital', true, false],
            'subdomain' => ['https://midia.cbngo.org.br/a.pdf', true, false],
            'uppercase host' => ['https://WWW.CBNGO.ORG.BR', true, false],
            'external' => ['https://www.convencaobatistanacional.com.br', true, true],
            'http external' => ['http://example.com/x', true, true],
            'look-alike host' => ['https://cbngo.org.br.evil.com', true, true],
            'suffix without dot' => ['https://fakecbngo.org.br', true, true],
            'protocol-relative' => ['//example.com', false, true],
            'javascript' => ['javascript:alert(1)', false, false],
            'mailto' => ['mailto:contato@cbngo.org.br', false, false],
            'plain text' => ['eventos', false, false],
            'empty' => ['', false, false],
            'null' => [null, false, false],
        ];
    }

    #[DataProvider('links')]
    public function test_validates_and_classifies_links(?string $url, bool $valid, bool $external): void
    {
        $this->assertSame($valid, LinkTarget::isValid($url));
        $this->assertSame($external, LinkTarget::isExternal($url));
    }
}
