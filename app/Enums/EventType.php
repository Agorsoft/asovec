<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EventType: string implements HasLabel
{
    case Evento = 'Evento';
    case Venta = 'Venta';

    public function getLabel(): string
    {
        return match ($this) {
            self::Evento => 'Evento',
            self::Venta => 'Venta especial',
        };
    }
}
