<?php

namespace App\Services\Documents;

use App\Models\DocumentTemplate;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Carga una plantilla DOCX, sustituye variables y genera el documento final.
 *
 * Usa el TemplateProcessor de PHPWord que trabaja con placeholders ${key}
 * dentro del XML interno del DOCX, manejando correctamente la fragmentación
 * de texto que Word genera.
 */
class DocxTemplateRenderer
{
    public function __construct(
        private readonly DocumentVariableRegistry $registry,
    ) {}

    /**
     * Genera un DOCX rellenado y lo devuelve como string de bytes.
     *
     * @param  array<string, mixed>  $context  Datos disponibles: 'member', 'quota', etc.
     */
    public function render(DocumentTemplate $template, array $context): string
    {
        $filePath = storage_path('app/private/' . $template->file_path);

        if (! file_exists($filePath)) {
            throw new \RuntimeException("Plantilla no encontrada: {$filePath}");
        }

        $processor = new TemplateProcessor($filePath);

        $detectedKeys = $template->detected_variables ?? [];
        $values = $this->registry->resolve($detectedKeys, $context);

        foreach ($values as $key => $value) {
            // PHPWord TemplateProcessor usa ${key} como placeholder.
            $processor->setValue($key, htmlspecialchars($value, ENT_XML1, 'UTF-8'));
        }

        // Guardar en un temporal y leerlo como string.
        $tempPath = tempnam(sys_get_temp_dir(), 'docx_');
        $processor->saveAs($tempPath);

        $bytes = file_get_contents($tempPath);
        @unlink($tempPath);

        if ($bytes === false) {
            throw new \RuntimeException('No se pudo leer el documento generado.');
        }

        return $bytes;
    }

    /**
     * Genera un DOCX y lo guarda en storage/app/private/documents/generated/.
     * Devuelve la ruta relativa almacenada.
     *
     * @param  array<string, mixed>  $context
     */
    public function renderAndSave(DocumentTemplate $template, array $context, string $filename): string
    {
        $bytes = $this->render($template, $context);

        $relativePath = 'documents/generated/' . $filename;
        $absolutePath = storage_path('app/private/' . $relativePath);

        if (! is_dir(dirname($absolutePath))) {
            mkdir(dirname($absolutePath), 0755, true);
        }

        file_put_contents($absolutePath, $bytes);

        return $relativePath;
    }
}
