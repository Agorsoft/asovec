<?php

namespace App\Services\Documents;

use ZipArchive;

/**
 * Parsea un archivo .docx y extrae los placeholders ${variable.campo}.
 *
 * Un .docx es un ZIP con XML interno. Abrimos directamente el XML
 * para evitar dependencias de entorno (sin LibreOffice, etc.).
 */
class DocxTemplateParser
{
    /** Regex que captura ${clave.campo} dentro del XML del DOCX. */
    private const PLACEHOLDER_PATTERN = '/\$\{([a-z][a-z0-9]*(?:\.[a-z][a-z0-9_]*)+)\}/i';

    /**
     * Analiza el DOCX en $filePath y devuelve los placeholders detectados.
     *
     * @return array{
     *   valid:   array<string>,
     *   invalid: array<string>,
     *   all:     array<string>,
     * }
     */
    public function parse(string $filePath, DocumentVariableRegistry $registry): array
    {
        $raw = $this->extractRawXml($filePath);
        $found = $this->extractPlaceholders($raw);

        $validKeys = $registry->validKeys();
        $valid = array_values(array_filter($found, fn (string $k) => in_array($k, $validKeys, true)));
        $invalid = array_values(array_filter($found, fn (string $k) => ! in_array($k, $validKeys, true)));

        return [
            'valid' => $valid,
            'invalid' => $invalid,
            'all' => $found,
        ];
    }

    /**
     * Extrae el XML crudo relevante del DOCX (word/document.xml y cabecera/pie si existen).
     */
    private function extractRawXml(string $filePath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($filePath) !== true) {
            throw new \RuntimeException("No se pudo abrir el archivo DOCX: {$filePath}");
        }

        $xmlParts = [];
        $targets = ['word/document.xml', 'word/header1.xml', 'word/header2.xml', 'word/footer1.xml', 'word/footer2.xml'];

        foreach ($targets as $target) {
            $content = $zip->getFromName($target);
            if ($content !== false) {
                $xmlParts[] = $content;
            }
        }

        $zip->close();

        if (empty($xmlParts)) {
            throw new \RuntimeException('El archivo DOCX no contiene word/document.xml.');
        }

        return implode(' ', $xmlParts);
    }

    /**
     * Aplica la regex sobre el XML (limpiando tags XML entre caracteres del placeholder)
     * y devuelve un array único de claves encontradas.
     *
     * Word fragmenta el texto en múltiples <w:t> lo que rompe placeholders.
     * Normalizamos eliminando tags XML antes de buscar para capturar fragmentados.
     *
     * @return array<string>
     */
    private function extractPlaceholders(string $xml): array
    {
        // Eliminar tags XML para obtener texto plano y detectar placeholders fragmentados.
        $plain = strip_tags($xml);

        preg_match_all(self::PLACEHOLDER_PATTERN, $plain, $matches);

        $keys = $matches[1] ?? [];

        // Deduplicar y normalizar a minúsculas.
        return array_values(array_unique(array_map('strtolower', $keys)));
    }

    /**
     * Extrae el texto legible del DOCX respetando saltos de párrafo.
     *
     * Cada <w:p> se convierte en una línea nueva para que el resultado
     * sea cómodo de leer en un textarea.
     */
    public function extractText(string $filePath): string
    {
        $zip = new ZipArchive;

        if ($zip->open($filePath) !== true) {
            return '';
        }

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return '';
        }

        // Insertar salto de línea al inicio de cada párrafo antes de eliminar tags.
        $xml = preg_replace('/<w:p[ >]/u', "\n<w:p>", $xml) ?? $xml;

        $text = strip_tags($xml);

        // Normalizar espacios y saltos redundantes.
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", trim($text)) ?? $text;

        return $text;
    }
}
