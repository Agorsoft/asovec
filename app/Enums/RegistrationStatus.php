<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegistrationStatus: string implements HasLabel
{
    case Pendiente = 'Pendiente';
    case Confirmada = 'Confirmada';
    case Cancelada = 'Cancelada';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Confirmada => 'success',
            self::Cancelada => 'danger',
        };
    }
}
