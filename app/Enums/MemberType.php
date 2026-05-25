<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MemberType: string implements HasLabel
{
    case Socio = 'Socio';
    case NoSocio = 'No Socio';

    public function getLabel(): string
    {
        return $this->value;
    }
}
