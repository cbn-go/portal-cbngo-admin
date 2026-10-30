<?php

namespace App\Services;

use App\DTOs\Statute\ArticleDiffItem;
use App\DTOs\Statute\StatuteArticle;
use App\DTOs\Statute\StatuteDiffResult;

class StatuteDiffService
{
    private AknStatuteParser $parser;

    public function __construct(?AknStatuteParser $parser = null)
    {
        $this->parser = $parser ?? new AknStatuteParser;
    }

    public function compare(string $oldXml, string $newXml): StatuteDiffResult
    {
        $oldStructure = $this->parser->parse($oldXml);
        $newStructure = $this->parser->parse($newXml);

        $oldArticles = [];
        foreach ($oldStructure->allArticles() as $art) {
            $key = $this->normalizeArticleKey($art);
            $oldArticles[$key] = $art;
        }

        $newArticles = [];
        foreach ($newStructure->allArticles() as $art) {
            $key = $this->normalizeArticleKey($art);
            $newArticles[$key] = $art;
        }

        $items = [];
        $added = 0;
        $modified = 0;
        $removed = 0;
        $unchanged = 0;

        // Processa novos e comparados
        foreach ($newArticles as $key => $newArt) {
            if (! isset($oldArticles[$key])) {
                $added++;
                $items[] = new ArticleDiffItem(
                    eId: $newArt->eId,
                    num: $newArt->num,
                    type: 'added',
                    oldText: null,
                    newText: $this->renderArticleFullText($newArt),
                    details: ['Artigo incluído nesta versão']
                );
            } else {
                $oldArt = $oldArticles[$key];
                $oldText = $this->renderArticleFullText($oldArt);
                $newText = $this->renderArticleFullText($newArt);

                if ($this->normalizeWhitespace($oldText) === $this->normalizeWhitespace($newText)) {
                    $unchanged++;
                    $items[] = new ArticleDiffItem(
                        eId: $newArt->eId,
                        num: $newArt->num,
                        type: 'unchanged',
                        oldText: $oldText,
                        newText: $newText
                    );
                } else {
                    $modified++;
                    $details = $this->extractDiffDetails($oldArt, $newArt);
                    $items[] = new ArticleDiffItem(
                        eId: $newArt->eId,
                        num: $newArt->num,
                        type: 'modified',
                        oldText: $oldText,
                        newText: $newText,
                        details: $details
                    );
                }
            }
        }

        // Processa removidos
        foreach ($oldArticles as $key => $oldArt) {
            if (! isset($newArticles[$key])) {
                $removed++;
                $items[] = new ArticleDiffItem(
                    eId: $oldArt->eId,
                    num: $oldArt->num,
                    type: 'removed',
                    oldText: $this->renderArticleFullText($oldArt),
                    newText: null,
                    details: ['Artigo revogado ou removido nesta versão']
                );
            }
        }

        return new StatuteDiffResult(
            articles: $items,
            addedCount: $added,
            modifiedCount: $modified,
            removedCount: $removed,
            unchangedCount: $unchanged
        );
    }

    private function normalizeArticleKey(StatuteArticle $art): string
    {
        // Se houver eId canônico (ex: art_8), usa o eId; senão normaliza pelo num
        if (! empty($art->eId)) {
            return strtolower(trim($art->eId));
        }

        return strtolower(preg_replace('/\s+/', '', $art->num) ?: 'art');
    }

    private function renderArticleFullText(StatuteArticle $art): string
    {
        $lines = [];
        $lines[] = trim($art->num.' '.(string) $art->introText);

        foreach ($art->elements as $el) {
            $num = $el->num ? trim($el->num).' ' : '';
            $text = trim((string) ($el->content ?? $el->introText));
            $lines[] = '  '.$num.$text;

            foreach ($el->subElements as $sub) {
                $subNum = $sub->num ? trim($sub->num).' ' : '';
                $subText = trim((string) ($sub->content ?? $sub->introText));
                $lines[] = '    '.$subNum.$subText;
            }
        }

        return implode("\n", array_filter($lines));
    }

    /**
     * @return array<int, string>
     */
    private function extractDiffDetails(StatuteArticle $old, StatuteArticle $new): array
    {
        $details = [];

        if ($this->normalizeWhitespace((string) $old->introText) !== $this->normalizeWhitespace((string) $new->introText)) {
            $details[] = 'Caput alterado';
        }

        if (count($old->elements) !== count($new->elements)) {
            $details[] = sprintf('Número de parágrafos/incisos alterado (%d para %d)', count($old->elements), count($new->elements));
        } else {
            $details[] = 'Parágrafos ou incisos alterados';
        }

        return $details;
    }

    private function normalizeWhitespace(string $text): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $text));
    }
}
