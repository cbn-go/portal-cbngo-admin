<?php

namespace App\Services;

use App\DTOs\Statute\StatuteArticle;
use App\DTOs\Statute\StatuteChapter;
use App\DTOs\Statute\StatuteElement;
use App\DTOs\Statute\StatuteSection;
use App\DTOs\Statute\StatuteStructure;
use DOMDocument;
use DOMElement;
use DOMXPath;

class AknStatuteParser
{
    private const NS = 'http://docs.oasis-open.org/legaldocml/ns/akn/3.0';

    public function parse(string $xml): StatuteStructure
    {
        $xpath = $this->createXPath($xml);

        $title = $this->queryText($xpath, '//akn:preface//akn:docTitle')
            ?: $this->queryAttr($xpath, '//akn:meta//akn:FRBRWork/akn:FRBRname', 'value')
            ?: 'Estatuto';

        $frbrWorkUri = $this->queryAttr($xpath, '//akn:meta//akn:FRBRWork/akn:FRBRthis', 'value');
        $frbrExpressionUri = $this->queryAttr($xpath, '//akn:meta//akn:FRBRExpression/akn:FRBRthis', 'value');
        $frbrManifestationUri = $this->queryAttr($xpath, '//akn:meta//akn:FRBRManifestation/akn:FRBRthis', 'value');
        $conclusions = $this->queryText($xpath, '//akn:conclusions//akn:p')
            ?: $this->queryText($xpath, '//akn:conclusions');

        $chapterNodes = $xpath->query('//akn:body/akn:chapter');
        $chapters = [];

        if ($chapterNodes) {
            foreach ($chapterNodes as $chpNode) {
                if ($chpNode instanceof DOMElement) {
                    $chapters[] = $this->parseChapter($xpath, $chpNode);
                }
            }
        }

        return new StatuteStructure(
            title: $title,
            frbrWorkUri: $frbrWorkUri,
            frbrExpressionUri: $frbrExpressionUri,
            frbrManifestationUri: $frbrManifestationUri,
            conclusions: $conclusions,
            chapters: $chapters
        );
    }

    public function countChapters(string $xml): int
    {
        $xpath = $this->createXPath($xml);
        $nodes = $xpath->query('//akn:body/akn:chapter');

        return $nodes ? $nodes->length : 0;
    }

    public function countSections(string $xml): int
    {
        $xpath = $this->createXPath($xml);
        $nodes = $xpath->query('//akn:body//akn:section');

        return $nodes ? $nodes->length : 0;
    }

    public function countArticles(string $xml): int
    {
        $xpath = $this->createXPath($xml);
        $nodes = $xpath->query('//akn:body//akn:article');

        return $nodes ? $nodes->length : 0;
    }

    /**
     * @return array<int, string>
     */
    public function getUniqueEIds(string $xml): array
    {
        $xpath = $this->createXPath($xml);
        $nodes = $xpath->query('//*[@eId]');
        $eids = [];

        if ($nodes) {
            foreach ($nodes as $node) {
                if ($node instanceof DOMElement) {
                    $eid = $node->getAttribute('eId');
                    if ($eid !== '' && ! in_array($eid, $eids, true)) {
                        $eids[] = $eid;
                    }
                }
            }
        }

        return $eids;
    }

    public function findArticleByEId(string $xml, string $eId): ?StatuteArticle
    {
        $xpath = $this->createXPath($xml);
        $nodes = $xpath->query(sprintf('//akn:body//akn:article[@eId="%s"]', addslashes($eId)));

        if ($nodes && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement) {
            return $this->parseArticle($xpath, $nodes->item(0));
        }

        return null;
    }

    private function parseChapter(DOMXPath $xpath, DOMElement $chpNode): StatuteChapter
    {
        $eId = $chpNode->getAttribute('eId');
        $num = $this->queryText($xpath, './akn:num', $chpNode) ?? '';
        $heading = $this->queryText($xpath, './akn:heading', $chpNode);

        // Seções
        $sectionNodes = $xpath->query('./akn:section', $chpNode);
        $sections = [];
        if ($sectionNodes) {
            foreach ($sectionNodes as $secNode) {
                if ($secNode instanceof DOMElement) {
                    $sections[] = $this->parseSection($xpath, $secNode);
                }
            }
        }

        // Artigos diretos do capítulo (fora de seções)
        $articleNodes = $xpath->query('./akn:article', $chpNode);
        $articles = [];
        if ($articleNodes) {
            foreach ($articleNodes as $artNode) {
                if ($artNode instanceof DOMElement) {
                    $articles[] = $this->parseArticle($xpath, $artNode);
                }
            }
        }

        return new StatuteChapter(
            eId: $eId,
            num: $num,
            heading: $heading,
            sections: $sections,
            articles: $articles
        );
    }

    private function parseSection(DOMXPath $xpath, DOMElement $secNode): StatuteSection
    {
        $eId = $secNode->getAttribute('eId');
        $num = $this->queryText($xpath, './akn:num', $secNode) ?? '';
        $heading = $this->queryText($xpath, './akn:heading', $secNode);

        $articleNodes = $xpath->query('./akn:article', $secNode);
        $articles = [];
        if ($articleNodes) {
            foreach ($articleNodes as $artNode) {
                if ($artNode instanceof DOMElement) {
                    $articles[] = $this->parseArticle($xpath, $artNode);
                }
            }
        }

        return new StatuteSection(
            eId: $eId,
            num: $num,
            heading: $heading,
            articles: $articles
        );
    }

    private function parseArticle(DOMXPath $xpath, DOMElement $artNode): StatuteArticle
    {
        $eId = $artNode->getAttribute('eId');
        $num = $this->queryText($xpath, './akn:num', $artNode) ?? '';
        $heading = $this->queryText($xpath, './akn:heading', $artNode);
        $introText = $this->queryText($xpath, './akn:intro//akn:p', $artNode)
            ?: $this->queryText($xpath, './akn:content//akn:p', $artNode);

        // Elementos filhos (parágrafos, pontos/incisos)
        $childElements = $xpath->query('./akn:paragraph | ./akn:point', $artNode);
        $elements = [];
        if ($childElements) {
            foreach ($childElements as $elNode) {
                if ($elNode instanceof DOMElement) {
                    $elements[] = $this->parseElement($xpath, $elNode);
                }
            }
        }

        return new StatuteArticle(
            eId: $eId,
            num: $num,
            heading: $heading,
            introText: $introText,
            elements: $elements
        );
    }

    private function parseElement(DOMXPath $xpath, DOMElement $elNode): StatuteElement
    {
        $eId = $elNode->getAttribute('eId');
        $type = $elNode->localName;
        $num = $this->queryText($xpath, './akn:num', $elNode);
        $introText = $this->queryText($xpath, './akn:intro//akn:p', $elNode);
        $content = $this->queryText($xpath, './akn:content//akn:p', $elNode);

        // Sub-elementos (pontos/incisos/alíneas ou subparágrafos)
        $subNodes = $xpath->query('./akn:point | ./akn:paragraph', $elNode);
        $subElements = [];
        if ($subNodes) {
            foreach ($subNodes as $subNode) {
                if ($subNode instanceof DOMElement) {
                    $subElements[] = $this->parseElement($xpath, $subNode);
                }
            }
        }

        return new StatuteElement(
            eId: $eId,
            type: $type,
            num: $num,
            introText: $introText,
            content: $content,
            subElements: $subElements
        );
    }

    private function createXPath(string $xml): DOMXPath
    {
        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = false;
        @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);

        $xpath = new DOMXPath($doc);
        $xpath->registerNamespace('akn', self::NS);

        return $xpath;
    }

    private function queryText(DOMXPath $xpath, string $query, ?DOMElement $context = null): ?string
    {
        $nodes = $context ? $xpath->query($query, $context) : $xpath->query($query);
        if ($nodes && $nodes->length > 0) {
            $text = trim($nodes->item(0)->textContent ?? '');

            return $text !== '' ? $text : null;
        }

        return null;
    }

    private function queryAttr(DOMXPath $xpath, string $query, string $attr, ?DOMElement $context = null): ?string
    {
        $nodes = $context ? $xpath->query($query, $context) : $xpath->query($query);
        if ($nodes && $nodes->length > 0 && $nodes->item(0) instanceof DOMElement) {
            $val = trim($nodes->item(0)->getAttribute($attr));

            return $val !== '' ? $val : null;
        }

        return null;
    }
}
