<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Efectivo = 'Efectivo';
    case Transferencia = 'Transferencia';
    case Bizum = 'Bizum';
    case Tarjeta = 'Tarjeta';
    case Domiciliacion = 'Domiciliación';

    public function label(): string
    {
        return $this->value;
    }
}
