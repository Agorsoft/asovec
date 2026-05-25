<?php

namespace App\Services;

use App\Enums\PdfEngine;
use Com\Tecnick\Pdf\Tcpdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

class PdfService
{
    public function __construct()
    {
        if (! defined('K_PATH_FONTS')) {
            define('K_PATH_FONTS', realpath(base_path('resources/fonts')));
        }
    }

    /**
     * Renders a Blade view to a raw PDF byte string.
     *
     * The $engine parameter controls which library is used:
     *  - PdfEngine::Dompdf  → mejor soporte HTML/CSS (recibos, documentos generales)
     *  - PdfEngine::Tcpdf   → posicionamiento por coordenadas (etiquetas)
     *
     * Page format and orientation are auto-detected from the view name suffix:
     * views ending in 'A5' produce A5 landscape, all others produce A4 portrait.
     *
     * @param  array<string, mixed>  $data
     */
    public function renderView(string $view, array $data, PdfEngine $engine = PdfEngine::Dompdf): string
    {
        $html = View::make($view, $data)->render();

        // Si la vista ya generó bytes PDF crudos, los devolvemos tal cual.
        if (str_starts_with(ltrim($html), '%PDF-')) {
            return $html;
        }

        return match ($engine) {
            PdfEngine::Dompdf => $this->renderWithDompdf($view, $html),
            PdfEngine::Tcpdf => $this->renderWithTcpdf($view, $html, $data),
        };
    }

    /**
     * Returns an inline PDF HTTP response streamed to the browser.
     */
    public function inlineResponse(string $rawPdf, string $filename): Response
    {
        return response($rawPdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.addslashes($filename).'"',
        ]);
    }

    private function renderWithDompdf(string $view, string $html): string
    {
        $isA5 = str_ends_with($view, 'A5');

        $options = new Options;
        $options->set('isRemoteEnabled', false);
        // Permitir que Dompdf lea archivos dentro del proyecto (storage, public, etc.)
        // sin exponer el sistema de archivos completo.
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $dompdf->setPaper($isA5 ? 'A5' : 'A4', $isA5 ? 'landscape' : 'portrait');
        $dompdf->loadHtml($html);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderWithTcpdf(string $view, string $html, array $data): string
    {
        $isA5 = str_ends_with($view, 'A5');
        $format = $isA5 ? 'A5' : 'A4';
        $orientation = $isA5 ? 'L' : 'P';

        $pdf = new Tcpdf(unit: 'mm');

        $meta = is_array($data['pdf_meta'] ?? null) ? $data['pdf_meta'] : [];
        foreach (['creator', 'author', 'title', 'keywords', 'filename'] as $k) {
            if (empty($meta[$k]) && ! empty($data[$k])) {
                $meta[$k] = $data[$k];
            }
        }

        if (! empty($meta['creator'])) {
            $pdf->setCreator($meta['creator']);
        }
        if (! empty($meta['author'])) {
            $pdf->setAuthor($meta['author']);
        }
        if (! empty($meta['title'])) {
            $pdf->setTitle($meta['title']);
        }
        if (! empty($meta['keywords'])) {
            $pdf->setKeywords($meta['keywords']);
        }
        if (! empty($meta['filename'])) {
            try {
                $pdf->setPDFFilename($meta['filename']);
            } catch (\Throwable) {
                // Some tc-lib-pdf versions may not implement this method; ignore.
            }
        }

        $bfont = $pdf->font->insert($pdf->pon, 'helvetica', '', 10);
        $pdf->addPage(['format' => $format, 'orientation' => $orientation]);
        $pdf->page->addContent($bfont['out']);

        $pdf->addHTMLCell(html: $html, posx: 0, posy: 0, width: 0);

        return $pdf->getOutPDFString();
    }
}
