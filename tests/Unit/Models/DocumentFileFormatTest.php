<?php

namespace Tests\Unit\Models;

use App\Models\Document;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DocumentFileFormatTest extends TestCase
{
    /**
     * @return array<string, array{0: string|null, 1: string|null, 2: string}>
     */
    public static function formats(): array
    {
        return [
            'pdf upload' => ['documents/files/abc.pdf', null, 'PDF'],
            'uppercase extension' => ['documents/files/abc.PDF', null, 'PDF'],
            'doc upload' => ['documents/files/abc.doc', null, 'DOC'],
            'docx upload' => ['documents/files/abc.docx', null, 'DOCX'],
            'file wins over link' => ['documents/files/abc.docx', 'https://cbngo.com.br/a.pdf', 'DOCX'],
            'link to pdf' => [null, 'https://cbngo.com.br/dados-abertos/estatuto.pdf', 'PDF'],
            'link to pdf with query' => [null, 'https://cbngo.com.br/estatuto.pdf?v=2', 'PDF'],
            'link to page' => [null, 'https://cbngo.com.br/pacto-renovado/', 'Página'],
            'nothing' => [null, null, 'PDF'],
        ];
    }

    #[DataProvider('formats')]
    public function test_detects_file_format(?string $file, ?string $externalUrl, string $expected): void
    {
        $this->assertSame($expected, Document::detectFileFormat($file, $externalUrl));
    }
}
