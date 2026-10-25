<?php

namespace Tests\Unit\Support;

use App\Support\AspectRatio;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AspectRatioTest extends TestCase
{
    /**
     * @return array<string, array{0: int, 1: int, 2: bool}>
     */
    public static function sizes(): array
    {
        return [
            'recommended' => [1920, 1080, true],
            'minimum' => [1280, 720, true],
            'first real art' => [1376, 768, true],
            'slightly off' => [1920, 1110, true],
            'too tall' => [1376, 900, false],
            'square' => [1080, 1080, false],
            'old 1920x800 hero' => [1920, 800, false],
            'invalid' => [0, 0, false],
        ];
    }

    #[DataProvider('sizes')]
    public function test_checks_16_9_with_5_percent_tolerance(int $width, int $height, bool $within): void
    {
        $this->assertSame($within, AspectRatio::isWithin($width, $height));
    }

    public function test_formats_ratio(): void
    {
        $this->assertSame('1,78:1', AspectRatio::format(1920, 1080));
        $this->assertSame('1,53:1', AspectRatio::format(1376, 900));
        $this->assertSame('—', AspectRatio::format(10, 0));
    }
}
