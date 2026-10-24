<?php

namespace App\Support;

/**
 * Estimativa de tempo de leitura exibida pelo portal público.
 */
class ReadingTime
{
    public const WORDS_PER_MINUTE = 200;

    /**
     * Calcula os minutos de leitura de um conteúdo HTML (mínimo de 1 minuto).
     */
    public static function fromHtml(?string $html): int
    {
        // Troca as tags por espaço para não colar palavras de blocos vizinhos (<p>a</p><p>b</p>).
        $text = strip_tags(str_replace('<', ' <', (string) $html));
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $count = $words === false ? 0 : count($words);

        return max(1, (int) ceil($count / self::WORDS_PER_MINUTE));
    }
}
