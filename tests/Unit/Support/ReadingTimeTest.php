<?php

namespace Tests\Unit\Support;

use App\Support\ReadingTime;
use PHPUnit\Framework\TestCase;

class ReadingTimeTest extends TestCase
{
    public function test_empty_or_short_content_takes_at_least_one_minute(): void
    {
        $this->assertSame(1, ReadingTime::fromHtml(null));
        $this->assertSame(1, ReadingTime::fromHtml(''));
        $this->assertSame(1, ReadingTime::fromHtml('<p>Graça e paz.</p>'));
    }

    public function test_rounds_up_by_words_per_minute(): void
    {
        $this->assertSame(1, ReadingTime::fromHtml(str_repeat('palavra ', 200)));
        $this->assertSame(2, ReadingTime::fromHtml(str_repeat('palavra ', 201)));
        $this->assertSame(2, ReadingTime::fromHtml(str_repeat('palavra ', 400)));
    }

    public function test_ignores_html_tags_and_does_not_join_adjacent_blocks(): void
    {
        $html = str_repeat('<p><strong>fé</strong></p><p>esperança</p>', 150);

        // 300 palavras: as tags não contam e os blocos vizinhos não se fundem.
        $this->assertSame(2, ReadingTime::fromHtml($html));
    }

    public function test_counts_accented_words_and_entities(): void
    {
        $html = '<p>'.str_repeat('Missões&nbsp;Família ', 101).'</p>';

        $this->assertSame(2, ReadingTime::fromHtml($html));
    }
}
