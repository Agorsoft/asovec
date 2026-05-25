<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RegistrationType: string implements HasLabel
{
    case Socio = 'Socio';
    case NoSocio = 'NoSocio';
    case Acompanante = 'Acompañante';

    public function getLabel(): string
    {
        return match ($this) {
            self::Socio => 'Socio',
            self::NoSocio => 'No socio',
            self::Acompanante => 'Acompañante',
        };
    }
}
