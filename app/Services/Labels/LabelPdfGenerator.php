<?php

namespace App\Services\Labels;

use App\DataTransferObjects\Labels\LabelPosition;
use App\DataTransferObjects\Labels\PrintJobConfig;
use App\Models\LabelTemplate;

/**
 * Genera el PDF de impresión de etiquetas postales usando tc-lib-pdf.
 *
 * Posicionamiento mediante coordenadas absolutas en mm.
 * Sin HTML, sin CSS, sin tablas.
 */
final class LabelPdfGenerator
{
    private readonly LabelGeometryCalculator $geometry;

    private readonly LabelContentBuilder $content;

    public function __construct()
    {
        $this->content = new LabelContentBuilder;

        if (! defined('K_PATH_FONTS')) {
            define(
                'K_PATH_FONTS',
                realpath(base_path('resources/fonts'))
            );
        }
    }

    /**
     * Genera el PDF completo y devuelve los bytes crudos.
     */
    public function generate(PrintJobConfig $config): string
    {
        $template = $config->template;
        $this->geometry = new LabelGeometryCalculator($template);

        $pdf = $this->initPdf($template);

        if ($config->testSheet) {
            $this->renderTestSheet($pdf, $template);
        } else {
            $this->renderLabels($pdf, $config);
        }

        return $pdf->getOutPDFString();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Inicialización del documento
    // ──────────────────────────────────────────────────────────────────────────

    private function initPdf(LabelTemplate $template): LabelTcpdf
    {
        $pdf = new LabelTcpdf(unit: 'mm');

        $pdf->setCreator('Asovec - Sistema de etiquetas');
        $pdf->setAuthor('Asovec');
        $pdf->setTitle('Etiquetas postales');
        $pdf->setKeywords('etiquetas socios postal');

        // Registrar la fuente principal (Helvetica — fuente base, sin embedding)
        $pdf->font->insert($pdf->pon, 'helvetica', '', $template->font_size);

        return $pdf;
    }

    private function addPageToPdf(LabelTcpdf $pdf, LabelTemplate $template): void
    {
        // Si es A4 estándar, usar el nombre de formato; si no, usar dimensiones custom
        $isA4 = abs($template->page_width_mm - 210.0) < 0.1
            && abs($template->page_height_mm - 297.0) < 0.1;

        $pdf->addPage($isA4
            ? ['format' => 'A4', 'orientation' => 'P']
            : ['format' => 'CUSTOM', 'orientation' => 'P', 'pheight' => $template->page_height_mm, 'pwidth' => $template->page_width_mm]
        );
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Renderizado de etiquetas reales
    // ──────────────────────────────────────────────────────────────────────────

    private function renderLabels(LabelTcpdf $pdf, PrintJobConfig $config): void
    {
        $template = $config->template;
        $startOffset = $config->startPosition - 1;
        $currentPage = -1;

        foreach ($config->members as $memberIndex => $member) {
            $globalIndex = $startOffset + $memberIndex;
            $position = $this->geometry->position($globalIndex);

            if ($position->page !== $currentPage) {
                $this->addPageToPdf($pdf, $template);
                $currentPage = $position->page;
            }

            $lines = $this->content->buildLines($member, $config->fields);
            $this->renderLabel($pdf, $position, $lines, $template);
        }
    }

    /**
     * Dibuja una etiqueta (todas sus líneas de texto) en la posición dada.
     *
     * Cada campo se pre-envuelve en PHP para conocer cuántas sub-líneas ocupa
     * antes de dibujar, evitando que un campo largo sobreescriba al siguiente.
     *
     * @param  string[]  $lines
     */
    private function renderLabel(
        LabelTcpdf $pdf,
        LabelPosition $position,
        array $lines,
        LabelTemplate $template,
    ): void {
        if ($lines === []) {
            return;
        }

        $padding = $template->inner_padding_mm;
        $lineHeightMm = $this->lineHeightMm($template);
        $usableWidth = $template->label_width_mm - (2 * $padding);
        $yMax = $position->y + $template->label_height_mm - $padding;
        $currentY = $position->y + $padding;

        foreach ($lines as $lineText) {
            // Pre-envolver en PHP: conocemos las sub-líneas antes de dibujar
            $subLines = $this->wrapLine($lineText, $usableWidth, $pdf);

            foreach ($subLines as $subLine) {
                if ($currentY + $lineHeightMm > $yMax + 0.01) {
                    return; // Sin espacio para más líneas en esta etiqueta
                }

                $pdf->addTextCell(
                    txt: $subLine,
                    posx: $position->x + $padding,
                    posy: $currentY,
                    width: $usableWidth,
                    height: $lineHeightMm,
                    valign: 'T',
                    halign: 'L',
                    drawcell: false,
                );

                $currentY += $lineHeightMm;
            }
        }
    }

    /**
     * Divide un texto en líneas que caben exactamente dentro del ancho útil
     * usando las métricas reales de la fuente activa (sin aproximaciones).
     *
     * Algoritmo:
     *  1. Si el texto completo cabe en una línea → devuelve [texto].
     *  2. Divide por palabras e intenta acumular la línea más larga posible.
     *  3. Si una palabra sola supera el ancho, la parte carácter a carácter.
     *
     * @return string[]
     */
    private function wrapLine(string $text, float $usableWidthMm, LabelTcpdf $pdf): array
    {
        if ($pdf->measureStringWidth($text) <= $usableWidthMm) {
            return [$text];
        }

        $words = explode(' ', $text);
        $lines = [];
        $currentLine = '';

        foreach ($words as $word) {
            $candidate = $currentLine === '' ? $word : $currentLine.' '.$word;

            if ($pdf->measureStringWidth($candidate) <= $usableWidthMm) {
                $currentLine = $candidate;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }

                $currentLine = $word;

                // Palabra individual demasiado larga: partir por caracteres
                while ($pdf->measureStringWidth($currentLine) > $usableWidthMm) {
                    $broken = false;
                    $len = mb_strlen($currentLine);

                    for ($i = $len - 1; $i > 0; $i--) {
                        $part = mb_substr($currentLine, 0, $i);

                        if ($pdf->measureStringWidth($part) <= $usableWidthMm) {
                            $lines[] = $part;
                            $currentLine = mb_substr($currentLine, $i);
                            $broken = true;
                            break;
                        }
                    }

                    if (! $broken) {
                        break; // Seguridad ante casos extremos
                    }
                }
            }
        }

        if ($currentLine !== '') {
            $lines[] = $currentLine;
        }

        return $lines !== [] ? $lines : [$text];
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Hoja de prueba (calibración de impresora)
    // ──────────────────────────────────────────────────────────────────────────

    private function renderTestSheet(LabelTcpdf $pdf, LabelTemplate $template): void
    {
        $this->addPageToPdf($pdf, $template);

        $total = $template->labels_per_page;

        for ($index = 0; $index < $total; $index++) {
            $position = $this->geometry->position($index);

            $this->drawLabelBorder($pdf, $position, $template);
            $this->renderTestLabel($pdf, $position, $index + 1, $template);
        }
    }

    private function drawLabelBorder(
        LabelTcpdf $pdf,
        LabelPosition $position,
        LabelTemplate $template,
    ): void {
        $style = [
            'all' => [
                'lineWidth' => 0.3,
                'lineCap' => 'square',
                'lineJoin' => 'miter',
                'dashArray' => [],
                'dashPhase' => 0,
                'lineColor' => 'gray',
            ],
        ];

        $rectContent = $pdf->graph->getRect(
            $position->x,
            $position->y,
            $template->label_width_mm,
            $template->label_height_mm,
            'S',
            $style,
        );

        $pdf->page->addContent($rectContent);
    }

    private function renderTestLabel(
        LabelTcpdf $pdf,
        LabelPosition $position,
        int $labelNumber,
        LabelTemplate $template,
    ): void {
        $lineHeightMm = $this->lineHeightMm($template);
        $padding = $template->inner_padding_mm;

        // Número de posición (centrado, tamaño grande)
        $pdf->font->insert($pdf->pon, 'helvetica', 'B', (int) round($template->font_size * 1.5));

        $pdf->addTextCell(
            txt: (string) $labelNumber,
            posx: $position->x,
            posy: $position->y,
            width: $template->label_width_mm,
            height: $template->label_height_mm * 0.5,
            valign: 'M',
            halign: 'C',
            drawcell: false,
        );

        // Coordenadas de referencia (tamaño pequeño)
        $pdf->font->insert($pdf->pon, 'helvetica', '', max(6, (int) round($template->font_size * 0.7)));

        $infoLine = sprintf(
            'F%d C%d — (%.1f, %.1f)',
            $position->row + 1,
            $position->column + 1,
            $position->x,
            $position->y,
        );

        $pdf->addTextCell(
            txt: $infoLine,
            posx: $position->x + $padding,
            posy: $position->y + $template->label_height_mm * 0.55,
            width: $template->label_width_mm - (2 * $padding),
            height: $lineHeightMm,
            valign: 'T',
            halign: 'C',
            drawcell: false,
        );

        // Restaurar fuente normal
        $pdf->font->insert($pdf->pon, 'helvetica', '', $template->font_size);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Utilidades de cálculo tipográfico
    // ──────────────────────────────────────────────────────────────────────────

    /** Altura de línea en mm según tamaño de fuente y multiplicador. */
    private function lineHeightMm(LabelTemplate $template): float
    {
        // 1 punto tipográfico = 25.4/72 mm
        $fontSizeMm = $template->font_size * (25.4 / 72);

        return round($fontSizeMm * $template->line_height, 4);
    }

    /** Número máximo de líneas que caben en una etiqueta con el padding dado. */
    private function maxLinesInLabel(LabelTemplate $template): int
    {
        $usableHeight = $template->label_height_mm - (2 * $template->inner_padding_mm);

        return max(1, (int) floor($usableHeight / $this->lineHeightMm($template)));
    }
}
