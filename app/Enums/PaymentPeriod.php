<?php

namespace App\Enums;

enum PaymentPeriod: string
{
    case Anual = 'Anual';
    case Semestral = 'Semestral';
    case Trimestral = 'Trimestral';
    case Mensual = 'Mensual';

    public function label(): string
    {
        return $this->value;
    }

    public function months(): int
    {
        return match ($this) {
            self::Mensual => 1,
            self::Trimestral => 3,
            self::Semestral => 6,
            self::Anual => 12,
        };
    }
}
