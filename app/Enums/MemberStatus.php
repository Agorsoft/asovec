<?php

namespace App\Enums;

enum MemberStatus: string
{
    case Activo = 'Activo';
    case Baja = 'Baja';
    case Pendiente = 'Pendiente';

    public function label(): string
    {
        return $this->value;
    }

    public function color(): string
    {
        return match ($this) {
            self::Pendiente => 'warning',
            self::Activo => 'success',
            self::Baja => 'danger',
        };
    }
}
