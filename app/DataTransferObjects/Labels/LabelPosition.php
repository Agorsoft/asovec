<?php

namespace App\DataTransferObjects\Labels;

/** Posición absoluta en mm de una etiqueta dentro de la página. */
final readonly class LabelPosition
{
    public function __construct(
        public float $x,
        public float $y,
        public int $page,        // página (0-based)
        public int $column,      // columna dentro de la página (0-based)
        public int $row,         // fila dentro de la página (0-based)
    ) {}
}
