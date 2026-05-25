<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SepaSequenceType: string implements HasLabel
{
    case PrimeraVez = 'Primera vez';
    case Recurrente = 'Recurrente';
    case Final = 'Final';
    case UnicaOcasion = 'Única ocasión';

    public function getLabel(): string
    {
        return $this->value;
    }
}
