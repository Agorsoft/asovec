<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Gender: string implements HasLabel
{
    case Hombre = 'Hombre';
    case Mujer = 'Mujer';
    case NoDefinido = 'No Definido';

    public function getLabel(): string
    {
        return $this->value;
    }
}
