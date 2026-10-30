<?php

namespace App\Services;

use App\Exceptions\AknValidationException;
use DOMDocument;
use DOMXPath;
use LibXMLError;

class AknValidator
{
    public const AKN_NAMESPACE = 'http://docs.oasis-open.org/legaldocml/ns/akn/3.0';

    private string $schemaPath;

    public function __construct(?string $schemaPath = null)
    {
        $this->schemaPath = $schemaPath ?? resource_path('schemas/akn/akomantoso30.xsd');
    }

    /**
     * Valida o XML contra o schema XSD do Akoma Ntoso 3.0 e a unicidade de eIds.
     *
     * @throws AknValidationException
     */
    public function validate(string $xml): void
    {
        $errors = $this->getErrors($xml);

        if (! empty($errors)) {
            $formatted = implode('; ', array_slice($errors, 0, 5));
            if (count($errors) > 5) {
                $formatted .= sprintf(' (e mais %d erros)', count($errors) - 5);
            }

            throw new AknValidationException(
                "O documento XML Akoma Ntoso 3.0 é inválido: {$formatted}",
                $errors
            );
        }
    }

    /**
     * Verifica se o XML é válido sem lançar exceção.
     */
    public function isValid(string $xml): bool
    {
        return empty($this->getErrors($xml));
    }

    /**
     * Retorna a lista de mensagens de erro encontradas na validação.
     *
     * @return array<int, string>
     */
    public function getErrors(string $xml): array
    {
        if (trim($xml) === '') {
            return ['O conteúdo XML está vazio.'];
        }

        if (! file_exists($this->schemaPath)) {
            return ["Arquivo de schema XSD não encontrado em: {$this->schemaPath}"];
        }

        $prevLibXmlUseErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $doc = new DOMDocument;
        $doc->preserveWhiteSpace = false;

        $loaded = @$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);

        if (! $loaded) {
            $errors = array_map(
                fn (LibXMLError $err) => sprintf('Linha %d: %s', $err->line, trim($err->message)),
                libxml_get_errors()
            );
            libxml_clear_errors();
            libxml_use_internal_errors($prevLibXmlUseErrors);

            return ! empty($errors) ? $errors : ['XML malformado e não pôde ser carregado.'];
        }

        $errors = [];

        // Validar elemento raiz e namespace
        $root = $doc->documentElement;
        if (! $root || $root->localName !== 'akomaNtoso' || $root->namespaceURI !== self::AKN_NAMESPACE) {
            $errors[] = sprintf(
                "Elemento raiz inválido: esperado <akomaNtoso> com namespace '%s', obtido <%s> com namespace '%s'.",
                self::AKN_NAMESPACE,
                $root ? $root->localName : 'null',
                $root ? ($root->namespaceURI ?? 'null') : 'null'
            );
        }

        // Validação XSD
        $schemaValid = @$doc->schemaValidate($this->schemaPath);
        if (! $schemaValid) {
            foreach (libxml_get_errors() as $err) {
                $errors[] = sprintf('Linha %d: %s', $err->line, trim($err->message));
            }
        }

        // Validação de unicidade de eId
        $duplicateErrors = $this->findDuplicateEIds($doc);
        if (! empty($duplicateErrors)) {
            $errors = array_merge($errors, $duplicateErrors);
        }

        libxml_clear_errors();
        libxml_use_internal_errors($prevLibXmlUseErrors);

        return $errors;
    }

    /**
     * @return array<int, string>
     */
    private function findDuplicateEIds(DOMDocument $doc): array
    {
        $xpath = new DOMXPath($doc);
        $nodes = $xpath->query('//*[@eId]');

        if (! $nodes) {
            return [];
        }

        $seen = [];
        $duplicates = [];

        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            $eid = $node->getAttribute('eId');
            if (isset($seen[$eid])) {
                $duplicates[$eid] = ($duplicates[$eid] ?? 1) + 1;
            } else {
                $seen[$eid] = true;
            }
        }

        $errors = [];
        foreach ($duplicates as $eid => $count) {
            $errors[] = sprintf("Identificador eId duplicado: '%s' encontrado %d vezes.", $eid, $count + 1);
        }

        return $errors;
    }
}
