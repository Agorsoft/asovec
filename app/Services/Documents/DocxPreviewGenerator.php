<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use PhpOffice\PhpWord\IOFactory;

/**
 * Genera y cachea una vista previa HTML de una plantilla DOCX.
 *
 * Compara el hash MD5 del archivo con el almacenado en el modelo.
 * Solo convierte el DOCX cuando el archivo cambia.
 */
class DocxPreviewGenerator
{
    /**
     * Devuelve el HTML de la vista previa, regenerándolo solo si el archivo cambió.
     */
    public function getOrGenerate(DocumentTemplate $template): ?string
    {
        if (! filled($template->file_path)) {
            return null;
        }

        $filePath = storage_path('app/private/'.$template->file_path);

        if (! file_exists($filePath)) {
            return null;
        }

        $hash = md5_file($filePath);

        if ($template->preview_hash === $hash && filled($template->preview_html)) {
            return $template->preview_html;
        }

        $html = $this->convertToHtml($filePath);

        $template->updateQuietly([
            'preview_hash' => $hash,
            'preview_html' => $html,
            'preview_generated_at' => now(),
        ]);

        return $html;
    }

    /**
     * Convierte el DOCX a HTML extrayendo solo el contenido del <body>.
     */
    private function convertToHtml(string $filePath): string
    {
        $phpWord = IOFactory::load($filePath);
        $writer = IOFactory::createWriter($phpWord, 'HTML');

        $tempPath = tempnam(sys_get_temp_dir(), 'docx_html_');
        $writer->save($tempPath);

        $fullHtml = (string) file_get_contents($tempPath);
        @unlink($tempPath);

        if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $fullHtml, $matches)) {
            return trim($matches[1]);
        }

        return $fullHtml;
    }
}
