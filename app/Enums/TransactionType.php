<?php

namespace App\Enums;

enum TransactionType: string
{
    case Ingreso = 'Ingreso';
    case Gasto = 'Gasto';

    public function label(): string
    {
        return $this->value;
    }

    public function color(): string
    {
        return match ($this) {
            self::Ingreso => 'success',
            self::Gasto => 'danger',
        };
    }
}
