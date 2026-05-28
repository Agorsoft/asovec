<?php

namespace App\DataTransferObjects\Labels;

use App\Models\LabelTemplate;
use App\Models\Member;
use Illuminate\Support\Collection;

/**
 * Configuración completa de un trabajo de impresión de etiquetas.
 *
 * @property-read Collection<int, Member> $members
 */
final readonly class PrintJobConfig
{
    /**
     * @param  Collection<int, Member>  $members  Socios a imprimir.
     * @param  array<int, string>  $fields  Valores de LabelField::value a imprimir.
     * @param  int  $startPosition  Posición inicial (1-based).
     * @param  bool  $testSheet  Imprimir hoja de prueba.
     */
    public function __construct(
        public LabelTemplate $template,
        public Collection $members,
        public array $fields,
        public int $startPosition = 1,
        public bool $testSheet = false,
    ) {}
}
