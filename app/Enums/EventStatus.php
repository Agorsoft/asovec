<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EventStatus: string implements HasLabel
{
    case Abierto = 'Abierto';
    case Cerrado = 'Cerrado';
    case Cancelado = 'Cancelado';
    case Finalizado = 'Finalizado';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function color(): string
    {
        return match ($this) {
            self::Abierto => 'success',
            self::Cerrado => 'gray',
            self::Cancelado => 'warning',
            self::Finalizado => 'primary',
        };
    }
}
