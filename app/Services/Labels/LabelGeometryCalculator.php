<?php

namespace App\Services\Labels;

use App\DataTransferObjects\Labels\LabelPosition;
use App\Models\LabelTemplate;

/**
 * Calcula la posición absoluta (mm) de cada etiqueta en la hoja.
 *
 * Algoritmo:
 *   globalIndex  = startOffset + memberIndex
 *   page         = floor(globalIndex / labelsPerPage)
 *   posOnPage    = globalIndex % labelsPerPage
 *   row          = floor(posOnPage / columns)
 *   col          = posOnPage  % columns
 *   x            = marginLeft + col * (labelWidth + horizontalGap)
 *   y            = marginTop  + row * (labelHeight + verticalGap)
 */
final class LabelGeometryCalculator
{
    public function __construct(private readonly LabelTemplate $template) {}

    /**
     * Calcula la posición de una etiqueta dado su índice global (0-based).
     *
     * @param int $globalIndex Índice global = startOffset + memberIndex
     */
    public function position(int $globalIndex): LabelPosition
    {
        $labelsPerPage = $this->template->labels_per_page;
        $columns = $this->template->columns;

        $page = intdiv($globalIndex, $labelsPerPage);
        $posOnPage = $globalIndex % $labelsPerPage;
        $row = intdiv($posOnPage, $columns);
        $col = $posOnPage % $columns;

        $x = $this->template->margin_left_mm
            + $col * ($this->template->label_width_mm + $this->template->horizontal_gap_mm);

        $y = $this->template->margin_top_mm
            + $row * ($this->template->label_height_mm + $this->template->vertical_gap_mm);

        return new LabelPosition(
            x: round($x, 4),
            y: round($y, 4),
            page: $page,
            column: $col,
            row: $row,
        );
    }

    /**
     * Genera todas las posiciones para los índices dados.
     *
     * @param  int[] $globalIndexes
     * @return LabelPosition[]
     */
    public function positions(array $globalIndexes): array
    {
        return array_map($this->position(...), $globalIndexes);
    }

    /** Número total de páginas necesarias para N etiquetas con un startOffset. */
    public function totalPages(int $memberCount, int $startOffset): int
    {
        if ($memberCount === 0) {
            return 0;
        }

        $lastGlobalIndex = $startOffset + $memberCount - 1;

        return intdiv($lastGlobalIndex, $this->template->labels_per_page) + 1;
    }
}
