<?php

namespace Tests\Unit\Models;

use App\Models\Municipality;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MunicipalityNormalizationTest extends TestCase
{
    #[DataProvider('normalizationCases')]
    public function test_normalizes_municipality_names_correctly(string $input, string $expected): void
    {
        $this->assertSame($expected, Municipality::normalizeName($input));
    }

    public static function normalizationCases(): array
    {
        return [
            'standard name' => ['Goiânia', 'goiania'],
            'uppercase with accent' => ['GOIANÉSIA', 'goianesia'],
            'lowercase with accent' => ['goianésia', 'goianesia'],
            'compound name with accents' => ['São Luís de Montes Belos', 'sao luis de montes belos'],
            'compound name with apostrophe' => ["São João d'Aliança", 'sao joao d alianca'],
            'compound name uppercase apostrophe' => ["SÍTIO D'ABADIA", 'sitio d abadia'],
            'irregular spacing' => ['  Aparecida   de   Goiânia  ', 'aparecida de goiania'],
            'hyphenated words' => ['Ceres - Rialma', 'ceres rialma'],
            'empty string' => ['', ''],
        ];
    }

    public function test_normalizes_null_returns_empty_string(): void
    {
        $this->assertSame('', Municipality::normalizeName(null));
    }
}
