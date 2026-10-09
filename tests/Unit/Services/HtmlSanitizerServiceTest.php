<?php

namespace Tests\Unit\Services;

use App\Services\HtmlSanitizerService;
use Tests\TestCase;

class HtmlSanitizerServiceTest extends TestCase
{
    private HtmlSanitizerService $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new HtmlSanitizerService;
    }

    public function test_it_handles_null_and_empty_strings(): void
    {
        $this->assertSame('', $this->sanitizer->sanitize(null));
        $this->assertSame('', $this->sanitizer->sanitize(''));
        $this->assertSame('', $this->sanitizer->sanitize('   '));
    }

    public function test_it_strips_script_tags_and_inline_javascript(): void
    {
        $dirty = '<p>Texto seguro</p><script>alert("xss")</script><script src="https://evil.com/evil.js"></script>';
        $cleaned = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('<p>Texto seguro</p>', $cleaned);
        $this->assertStringNotContainsString('<script', $cleaned);
        $this->assertStringNotContainsString('alert("xss")', $cleaned);
        $this->assertStringNotContainsString('evil.com', $cleaned);
    }

    public function test_it_strips_javascript_pseudo_protocols_in_links(): void
    {
        $dirty = '<p><a href="javascript:alert(1)">Clique aqui</a> e <a href="vbscript:msgbox(1)">outro</a></p>';
        $cleaned = $this->sanitizer->sanitize($dirty);

        $this->assertStringContainsString('Clique aqui', $cleaned);
        $this->assertStringNotContainsString('javascript:alert(1)', $cleaned);
        $this->assertStringNotContainsString('vbscript:msgbox(1)', $cleaned);
    }

    public function test_it_strips_inline_event_handlers(): void
    {
        $dirty = '<img src="/storage/test.jpg" onerror="alert(1)" onload="evil()"><div onclick="steal()">Clique</div>';
        $cleaned = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('onerror', $cleaned);
        $this->assertStringNotContainsString('onload', $cleaned);
        $this->assertStringNotContainsString('onclick', $cleaned);
        $this->assertStringNotContainsString('alert(1)', $cleaned);
    }

    public function test_it_preserves_safe_formatting_elements(): void
    {
        $html = '<h2>Título 2</h2><h3>Título 3</h3><h4>Título 4</h4><p>Parágrafo com <strong>negrito</strong>, <em>itálico</em>, <u>sublinhado</u> e <s>tachado</s>.</p>';
        $cleaned = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<h2>Título 2</h2>', $cleaned);
        $this->assertStringContainsString('<h3>Título 3</h3>', $cleaned);
        $this->assertStringContainsString('<h4>Título 4</h4>', $cleaned);
        $this->assertStringContainsString('<strong>negrito</strong>', $cleaned);
        $this->assertStringContainsString('<em>itálico</em>', $cleaned);
        $this->assertStringContainsString('<u>sublinhado</u>', $cleaned);
    }

    public function test_it_preserves_blockquotes_for_bible_verses(): void
    {
        $html = '<blockquote class="verse">Porque Deus amou o mundo de tal maneira... (João 3:16)</blockquote>';
        $cleaned = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<blockquote>', $cleaned);
        $this->assertStringContainsString('João 3:16', $cleaned);
    }

    public function test_it_preserves_lists(): void
    {
        $html = '<ul><li>Primeiro ponto</li><li>Segundo ponto</li></ul><ol><li>Etapa 1</li><li>Etapa 2</li></ol>';
        $cleaned = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<ul><li>Primeiro ponto</li>', $cleaned);
        $this->assertStringContainsString('<ol><li>Etapa 1</li>', $cleaned);
    }

    public function test_it_preserves_safe_images_and_links_with_security_rel(): void
    {
        $html = '<p><a href="https://cbngo.com.br" target="_blank">Portal CBN-GO</a></p><img src="/storage/articles/attachments/foto.webp" alt="Foto Ilustrativa">';
        $cleaned = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('href="https://cbngo.com.br"', $cleaned);
        $this->assertStringContainsString('rel="noopener noreferrer"', $cleaned);
        $this->assertStringContainsString('src="/storage/articles/attachments/foto.webp"', $cleaned);
        $this->assertStringContainsString('alt="Foto Ilustrativa"', $cleaned);
    }

    public function test_it_strips_data_scheme_in_media_elements(): void
    {
        $dirty = '<p>Imagem</p><img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==" alt="Data URI">';
        $cleaned = $this->sanitizer->sanitize($dirty);

        $this->assertStringNotContainsString('data:image', $cleaned);
    }

    public function test_it_preserves_pre_and_code_blocks(): void
    {
        $html = '<pre><code>echo "Glória a Deus";</code></pre>';
        $cleaned = $this->sanitizer->sanitize($html);

        $this->assertStringContainsString('<pre>', $cleaned);
        $this->assertStringContainsString('<code>', $cleaned);
        $this->assertStringContainsString('Glória a Deus', $cleaned);
    }
}
