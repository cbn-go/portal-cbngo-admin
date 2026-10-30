<?php

namespace App\Services;

use App\DTOs\Statute\StatuteArticle;
use App\DTOs\Statute\StatuteElement;
use DOMDocument;
use DOMElement;

class AknStatuteSerializer
{
    private const NS = 'http://docs.oasis-open.org/legaldocml/ns/akn/3.0';

    private AknStatuteParser $parser;

    public function __construct(?AknStatuteParser $parser = null)
    {
        $this->parser = $parser ?? new AknStatuteParser;
    }

    /**
     * Converte o XML em array de dados para o formulário estruturado do Filament.
     *
     * @return array<string, mixed>
     */
    public function toFormData(string $xml): array
    {
        $structure = $this->parser->parse($xml);

        $chapters = [];
        foreach ($structure->chapters as $chapter) {
            $sections = [];
            foreach ($chapter->sections as $section) {
                $sectionArticles = [];
                foreach ($section->articles as $art) {
                    $sectionArticles[] = $this->articleToFormData($art);
                }

                $sections[] = [
                    'eId' => $section->eId,
                    'num' => $section->num,
                    'heading' => $section->heading,
                    'articles' => $sectionArticles,
                ];
            }

            $articles = [];
            foreach ($chapter->articles as $art) {
                $articles[] = $this->articleToFormData($art);
            }

            $chapters[] = [
                'eId' => $chapter->eId,
                'num' => $chapter->num,
                'heading' => $chapter->heading,
                'has_sections' => ! empty($sections),
                'sections' => $sections,
                'articles' => $articles,
            ];
        }

        return [
            'chapters' => $chapters,
        ];
    }

    /**
     * Converte o array de dados do formulário e metadados em XML Akoma Ntoso 3.0 canônico.
     *
     * @param  array<string, mixed>  $formData
     * @param  array<string, mixed>  $metadata
     */
    public function toXml(array $formData, array $metadata): string
    {
        $title = $metadata['title'] ?? 'Estatuto da Convenção Batista Nacional do Estado de Goiás';
        $approvedAt = $metadata['approved_at'] ?? '2025-10-18';
        if ($approvedAt instanceof \DateTimeInterface) {
            $approvedAt = $approvedAt->format('Y-m-d');
        } elseif (is_string($approvedAt) && strlen($approvedAt) > 10) {
            $approvedAt = substr($approvedAt, 0, 10);
        }

        $approvedPlace = $metadata['approved_place'] ?? 'Anápolis, GO';
        $year = substr((string) $approvedAt, 0, 4) ?: '2025';

        $workUri = "/akn/br/act/estatuto/cbngo/{$approvedAt}";
        $exprUri = "{$workUri}/por@{$approvedAt}";
        $maniUri = "{$exprUri}/main.xml";

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS(self::NS, 'akomaNtoso');
        $doc->appendChild($root);

        $act = $doc->createElement('act');
        $act->setAttribute('name', 'estatuto');
        $root->appendChild($act);

        // 1. <meta>
        $meta = $doc->createElement('meta');
        $act->appendChild($meta);

        $ident = $doc->createElement('identification');
        $ident->setAttribute('source', '#cbngo');
        $meta->appendChild($ident);

        // FRBRWork
        $frbrWork = $doc->createElement('FRBRWork');
        $this->addAttrElement($doc, $frbrWork, 'FRBRthis', 'value', $workUri);
        $this->addAttrElement($doc, $frbrWork, 'FRBRuri', 'value', $workUri);
        $this->addAttrElement($doc, $frbrWork, 'FRBRdate', ['date' => $approvedAt, 'name' => 'approval']);
        $this->addAttrElement($doc, $frbrWork, 'FRBRauthor', ['href' => '#cbngo', 'as' => '#author']);
        $this->addAttrElement($doc, $frbrWork, 'FRBRcountry', 'value', 'br');
        $this->addAttrElement($doc, $frbrWork, 'FRBRsubtype', 'value', 'statute');
        $this->addAttrElement($doc, $frbrWork, 'FRBRnumber', 'value', $year);
        $this->addAttrElement($doc, $frbrWork, 'FRBRname', 'value', $title);
        $ident->appendChild($frbrWork);

        // FRBRExpression
        $frbrExpr = $doc->createElement('FRBRExpression');
        $this->addAttrElement($doc, $frbrExpr, 'FRBRthis', 'value', $exprUri);
        $this->addAttrElement($doc, $frbrExpr, 'FRBRuri', 'value', $exprUri);
        $this->addAttrElement($doc, $frbrExpr, 'FRBRdate', ['date' => $approvedAt, 'name' => 'approval']);
        $this->addAttrElement($doc, $frbrExpr, 'FRBRauthor', ['href' => '#cbngo', 'as' => '#editor']);
        $this->addAttrElement($doc, $frbrExpr, 'FRBRlanguage', 'language', 'por');
        $ident->appendChild($frbrExpr);

        // FRBRManifestation
        $frbrMani = $doc->createElement('FRBRManifestation');
        $this->addAttrElement($doc, $frbrMani, 'FRBRthis', 'value', $maniUri);
        $this->addAttrElement($doc, $frbrMani, 'FRBRuri', 'value', "{$exprUri}.akn");
        $this->addAttrElement($doc, $frbrMani, 'FRBRdate', ['date' => $approvedAt, 'name' => 'generation']);
        $this->addAttrElement($doc, $frbrMani, 'FRBRauthor', ['href' => '#cbngo', 'as' => '#publisher']);
        $this->addAttrElement($doc, $frbrMani, 'FRBRformat', 'value', 'xml');
        $ident->appendChild($frbrMani);

        // lifecycle
        $lifecycle = $doc->createElement('lifecycle');
        $lifecycle->setAttribute('source', '#cbngo');
        $this->addAttrElement($doc, $lifecycle, 'eventRef', [
            'date' => $approvedAt,
            'type' => 'generation',
            'eId' => 'approved',
            'source' => '#cbngo',
        ]);
        $meta->appendChild($lifecycle);

        // references
        $references = $doc->createElement('references');
        $references->setAttribute('source', '#cbngo');
        $this->addAttrElement($doc, $references, 'TLCOrganization', [
            'eId' => 'cbngo',
            'href' => '/akn/ontology/organization/br/cbngo',
            'showAs' => 'Convenção Batista Nacional do Estado de Goiás',
        ]);
        $this->addAttrElement($doc, $references, 'TLCRole', ['eId' => 'author', 'href' => '/akn/ontology/role/author', 'showAs' => 'Autor']);
        $this->addAttrElement($doc, $references, 'TLCRole', ['eId' => 'editor', 'href' => '/akn/ontology/role/editor', 'showAs' => 'Editor']);
        $this->addAttrElement($doc, $references, 'TLCRole', ['eId' => 'publisher', 'href' => '/akn/ontology/role/publisher', 'showAs' => 'Publicador']);
        $meta->appendChild($references);

        // 2. <preface>
        $preface = $doc->createElement('preface');
        $p = $doc->createElement('p');
        $docTitle = $doc->createElement('docTitle', htmlspecialchars($title, ENT_XML1, 'UTF-8'));
        $p->appendChild($docTitle);
        $preface->appendChild($p);
        $act->appendChild($preface);

        // 3. <body>
        $body = $doc->createElement('body');
        $act->appendChild($body);

        $chapters = $formData['chapters'] ?? [];
        $articleIndex = 1;

        $usedEids = [];

        foreach ($chapters as $chpIdx => $chpData) {
            $chpNum = $chpData['num'] ?? sprintf('CAPÍTULO %d', $chpIdx + 1);
            $rawChpEid = trim((string) ($chpData['eId'] ?? ''));
            if ($rawChpEid !== '' && ! isset($usedEids[$rawChpEid])) {
                $chpEid = $rawChpEid;
            } else {
                $chpEid = sprintf('chp_%d', $chpIdx + 1);
                $seq = 1;
                $baseChpEid = $chpEid;
                while (isset($usedEids[$chpEid])) {
                    $chpEid = "{$baseChpEid}_{$seq}";
                    $seq++;
                }
            }
            $usedEids[$chpEid] = true;

            $chpElem = $doc->createElement('chapter');
            $chpElem->setAttribute('eId', $chpEid);
            $chpElem->appendChild($doc->createElement('num', htmlspecialchars((string) $chpNum, ENT_XML1, 'UTF-8')));

            if (! empty($chpData['heading'])) {
                $chpElem->appendChild($doc->createElement('heading', htmlspecialchars((string) $chpData['heading'], ENT_XML1, 'UTF-8')));
            }

            // Artigos diretos do capítulo
            foreach ($chpData['articles'] ?? [] as $artData) {
                $artElem = $this->buildArticleElement($doc, $artData, $articleIndex, $usedEids);
                $chpElem->appendChild($artElem);
                $articleIndex++;
            }

            // Seções do capítulo
            foreach ($chpData['sections'] ?? [] as $secIdx => $secData) {
                $secNum = $secData['num'] ?? sprintf('SEÇÃO %d', $secIdx + 1);
                $rawSecEid = trim((string) ($secData['eId'] ?? ''));
                if ($rawSecEid !== '' && ! isset($usedEids[$rawSecEid])) {
                    $secEid = $rawSecEid;
                } else {
                    $secEid = sprintf('%s__sec_%d', $chpEid, $secIdx + 1);
                    $seq = 1;
                    $baseSecEid = $secEid;
                    while (isset($usedEids[$secEid])) {
                        $secEid = "{$baseSecEid}_{$seq}";
                        $seq++;
                    }
                }
                $usedEids[$secEid] = true;

                $secElem = $doc->createElement('section');
                $secElem->setAttribute('eId', $secEid);
                $secElem->appendChild($doc->createElement('num', htmlspecialchars((string) $secNum, ENT_XML1, 'UTF-8')));

                if (! empty($secData['heading'])) {
                    $secElem->appendChild($doc->createElement('heading', htmlspecialchars((string) $secData['heading'], ENT_XML1, 'UTF-8')));
                }

                foreach ($secData['articles'] ?? [] as $artData) {
                    $artElem = $this->buildArticleElement($doc, $artData, $articleIndex, $usedEids);
                    $secElem->appendChild($artElem);
                    $articleIndex++;
                }

                $chpElem->appendChild($secElem);
            }

            $body->appendChild($chpElem);
        }

        // 4. <conclusions>
        $conclusions = $doc->createElement('conclusions');
        $concP = $doc->createElement('p');

        $formattedDate = $this->formatDatePtBr($approvedAt);
        $concP->textContent = sprintf('%s, %s.', $approvedPlace, $formattedDate);
        $conclusions->appendChild($concP);
        $act->appendChild($conclusions);

        return $doc->saveXML() ?: '';
    }

    /**
     * @return array<string, mixed>
     */
    private function articleToFormData(StatuteArticle $art): array
    {
        $paragraphs = [];
        $points = [];

        foreach ($art->elements as $el) {
            if ($el->type === 'paragraph') {
                $paragraphs[] = $this->elementToFormData($el);
            } elseif ($el->type === 'point') {
                $points[] = $this->elementToFormData($el);
            }
        }

        return [
            'eId' => $art->eId,
            'num' => $art->num,
            'heading' => $art->heading,
            'intro' => $art->introText,
            'paragraphs' => $paragraphs,
            'points' => $points,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function elementToFormData(StatuteElement $el): array
    {
        $subPoints = [];
        $subParagraphs = [];

        foreach ($el->subElements as $sub) {
            if ($sub->type === 'point') {
                $subPoints[] = $this->elementToFormData($sub);
            } else {
                $subParagraphs[] = $this->elementToFormData($sub);
            }
        }

        return [
            'eId' => $el->eId,
            'num' => $el->num,
            'intro' => $el->introText,
            'content' => $el->content ?? $el->introText,
            'points' => $subPoints,
            'paragraphs' => $subParagraphs,
        ];
    }

    /**
     * @param  array<string, mixed>  $artData
     * @param  array<string, bool>  $usedEids
     */
    private function buildArticleElement(DOMDocument $doc, array $artData, int $fallbackIndex, array &$usedEids): DOMElement
    {
        $artNum = $artData['num'] ?? sprintf('Art. %dº', $fallbackIndex);
        $rawEid = trim((string) ($artData['eId'] ?? ''));

        if ($rawEid !== '' && ! isset($usedEids[$rawEid])) {
            $artEid = $rawEid;
        } else {
            $artEid = $this->generateUniqueArticleEid($artNum, $fallbackIndex, $usedEids);
        }

        $usedEids[$artEid] = true;

        $artElem = $doc->createElement('article');
        $artElem->setAttribute('eId', $artEid);
        $artElem->appendChild($doc->createElement('num', htmlspecialchars((string) $artNum, ENT_XML1, 'UTF-8')));

        if (! empty($artData['heading'])) {
            $artElem->appendChild($doc->createElement('heading', htmlspecialchars((string) $artData['heading'], ENT_XML1, 'UTF-8')));
        }

        $introText = trim((string) ($artData['intro'] ?? ''));
        $paragraphs = $artData['paragraphs'] ?? [];
        $points = $artData['points'] ?? [];

        $hasChildren = (! empty($paragraphs) || ! empty($points));

        if ($hasChildren) {
            if ($introText !== '') {
                $intro = $doc->createElement('intro');
                $p = $doc->createElement('p', htmlspecialchars($introText, ENT_XML1, 'UTF-8'));
                $intro->appendChild($p);
                $artElem->appendChild($intro);
            }

            // Pontos diretos (incisos no caput)
            foreach ($points as $ptIdx => $ptData) {
                $ptElem = $this->buildPointElement($doc, $ptData, $artEid, $ptIdx + 1, $usedEids);
                $artElem->appendChild($ptElem);
            }

            // Parágrafos do artigo
            foreach ($paragraphs as $paraIdx => $paraData) {
                $paraElem = $this->buildParagraphElement($doc, $paraData, $artEid, $paraIdx + 1, $usedEids);
                $artElem->appendChild($paraElem);
            }
        } else {
            // Artigo sem filhos usa <content>
            $content = $doc->createElement('content');
            $p = $doc->createElement('p', htmlspecialchars($introText, ENT_XML1, 'UTF-8'));
            $content->appendChild($p);
            $artElem->appendChild($content);
        }

        return $artElem;
    }

    /**
     * @param  array<string, mixed>  $paraData
     * @param  array<string, bool>  $usedEids
     */
    private function buildParagraphElement(DOMDocument $doc, array $paraData, string $parentEid, int $index, array &$usedEids): DOMElement
    {
        $paraNum = $paraData['num'] ?? sprintf('§ %dº -', $index);
        $rawEid = trim((string) ($paraData['eId'] ?? ''));

        if ($rawEid !== '' && ! isset($usedEids[$rawEid])) {
            $paraEid = $rawEid;
        } else {
            $paraEid = sprintf('%s__para_%d', $parentEid, $index);
            $seq = 1;
            $baseEid = $paraEid;
            while (isset($usedEids[$paraEid])) {
                $paraEid = "{$baseEid}_{$seq}";
                $seq++;
            }
        }
        $usedEids[$paraEid] = true;

        $paraElem = $doc->createElement('paragraph');
        $paraElem->setAttribute('eId', $paraEid);

        if ($paraNum !== '') {
            $paraElem->appendChild($doc->createElement('num', htmlspecialchars((string) $paraNum, ENT_XML1, 'UTF-8')));
        }

        $text = trim((string) ($paraData['content'] ?? $paraData['intro'] ?? ''));
        $subPoints = $paraData['points'] ?? [];

        if (! empty($subPoints)) {
            if ($text !== '') {
                $intro = $doc->createElement('intro');
                $intro->appendChild($doc->createElement('p', htmlspecialchars($text, ENT_XML1, 'UTF-8')));
                $paraElem->appendChild($intro);
            }

            foreach ($subPoints as $idx => $pt) {
                $paraElem->appendChild($this->buildPointElement($doc, $pt, $paraEid, $idx + 1, $usedEids));
            }
        } else {
            $content = $doc->createElement('content');
            $content->appendChild($doc->createElement('p', htmlspecialchars($text, ENT_XML1, 'UTF-8')));
            $paraElem->appendChild($content);
        }

        return $paraElem;
    }

    /**
     * @param  array<string, mixed>  $ptData
     * @param  array<string, bool>  $usedEids
     */
    private function buildPointElement(DOMDocument $doc, array $ptData, string $parentEid, int $index, array &$usedEids): DOMElement
    {
        $ptNum = $ptData['num'] ?? sprintf('%d -', $index);
        $rawEid = trim((string) ($ptData['eId'] ?? ''));

        if ($rawEid !== '' && ! isset($usedEids[$rawEid])) {
            $ptEid = $rawEid;
        } else {
            $ptEid = sprintf('%s__point_%d', $parentEid, $index);
            $seq = 1;
            $baseEid = $ptEid;
            while (isset($usedEids[$ptEid])) {
                $ptEid = "{$baseEid}_{$seq}";
                $seq++;
            }
        }
        $usedEids[$ptEid] = true;

        $ptElem = $doc->createElement('point');
        $ptElem->setAttribute('eId', $ptEid);

        if ($ptNum !== '') {
            $ptElem->appendChild($doc->createElement('num', htmlspecialchars((string) $ptNum, ENT_XML1, 'UTF-8')));
        }

        $text = trim((string) ($ptData['content'] ?? $ptData['intro'] ?? ''));
        $subPoints = $ptData['points'] ?? [];

        if (! empty($subPoints)) {
            if ($text !== '') {
                $intro = $doc->createElement('intro');
                $intro->appendChild($doc->createElement('p', htmlspecialchars($text, ENT_XML1, 'UTF-8')));
                $ptElem->appendChild($intro);
            }

            foreach ($subPoints as $idx => $sub) {
                $ptElem->appendChild($this->buildPointElement($doc, $sub, $ptEid, $idx + 1, $usedEids));
            }
        } else {
            $content = $doc->createElement('content');
            $content->appendChild($doc->createElement('p', htmlspecialchars($text, ENT_XML1, 'UTF-8')));
            $ptElem->appendChild($content);
        }

        return $ptElem;
    }

    /**
     * @param  string|array<string, string>  $attrOrMap
     */
    private function addAttrElement(DOMDocument $doc, DOMElement $parent, string $name, string|array $attrOrMap, ?string $value = null): void
    {
        $el = $doc->createElement($name);
        if (is_array($attrOrMap)) {
            foreach ($attrOrMap as $k => $v) {
                $el->setAttribute($k, (string) $v);
            }
        } elseif ($value !== null) {
            $el->setAttribute($attrOrMap, $value);
        }
        $parent->appendChild($el);
    }

    private function formatDatePtBr(string $date): string
    {
        $parts = explode('-', $date);
        if (count($parts) !== 3) {
            return $date;
        }

        $day = (int) $parts[2];
        $monthNum = (int) $parts[1];
        $year = $parts[0];

        $months = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro',
        ];

        $monthName = $months[$monthNum] ?? 'outubro';

        return sprintf('%d de %s de %s', $day, $monthName, $year);
    }

    /**
     * @param  array<string, bool>  $usedEids
     */
    private function generateUniqueArticleEid(string $num, int $fallbackIndex, array $usedEids): string
    {
        $cleaned = strtolower($num);
        $cleaned = (string) preg_replace('/^art\.?\s*/i', '', $cleaned);
        $cleaned = str_replace(['º', 'ª', '°', '.', '-'], ['_', '_', '_', '_', '_'], $cleaned);
        $cleaned = (string) preg_replace('/[^a-z0-9_]/', '', $cleaned);
        $cleaned = trim($cleaned, '_');

        $base = $cleaned !== '' ? "art_{$cleaned}" : "art_{$fallbackIndex}";

        if (! isset($usedEids[$base])) {
            return $base;
        }

        $seq = 1;
        while (isset($usedEids["{$base}_{$seq}"])) {
            $seq++;
        }

        return "{$base}_{$seq}";
    }
}
