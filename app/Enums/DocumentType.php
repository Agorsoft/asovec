<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DocumentType: string implements HasLabel
{
    case Carta = 'Carta';
    case Certificado = 'Certificado';
    case Recibo = 'Recibo';
    case Otro = 'Otro';

    public function getLabel(): string
    {
        return $this->value;
    }
}
