<?php

namespace App\Enums;

enum QuotaStatus: string
{
    case Pendiente = 'Pendiente';
    case Pagada = 'Pagada';
    case Vencida = 'Vencida';
    case Cancelada = 'Cancelada';

    public function label(): string
    {
        return $this->value;
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Pagada => 'success',
            self::Vencida => 'danger',
            self::Cancelada => 'gray',
        };
    }
}
