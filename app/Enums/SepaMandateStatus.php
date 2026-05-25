<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SepaMandateStatus: string implements HasLabel
{
    case PendienteDeFirma = 'Pendiente de firma';
    case Firmado = 'Firmado';
    case Revocado = 'Revocado';

    public function getLabel(): string
    {
        return $this->value;
    }

    public function color(): string
    {
        return match ($this) {
            self::PendienteDeFirma => 'warning',
            self::Firmado => 'success',
            self::Revocado => 'danger',
        };
    }
}
