<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

class HtmlSanitizerService
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowElement('img', ['src', 'alt', 'title', 'class', 'width', 'height', 'loading'])
            ->allowElement('blockquote', ['cite'])
            ->allowElement('figure', ['class'])
            ->allowElement('figcaption', ['class'])
            ->allowElement('a', ['href', 'title', 'target', 'rel', 'class'])
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('strike')
            ->allowElement('h1', ['class'])
            ->allowElement('h2', ['class'])
            ->allowElement('h3', ['class'])
            ->allowElement('h4', ['class'])
            ->allowElement('h5', ['class'])
            ->allowElement('h6', ['class'])
            ->allowElement('p', ['class'])
            ->allowElement('div', ['class'])
            ->allowElement('span', ['class'])
            ->allowElement('hr')
            ->allowElement('br')
            ->allowRelativeMedias()
            ->allowRelativeLinks()
            ->forceAttribute('a', 'rel', 'noopener noreferrer')
            ->allowMediaSchemes(['http', 'https'])
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel']);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Sanitiza uma string HTML removendo scripts, manipuladores inline de evento e protocolos perigosos.
     */
    public function sanitize(?string $html): string
    {
        if ($html === null || trim($html) === '') {
            return '';
        }

        // Remove expressamente pseudo-protocolos perigosos em tags restantes (como vbscript:)
        $sanitized = preg_replace('/href=[\'"]\s*(?:vbscript|data):[^\'"]*[\'"]/i', '', $html) ?? $html;

        return $this->sanitizer->sanitize($sanitized);
    }
}
