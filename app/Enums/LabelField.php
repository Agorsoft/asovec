<?php

namespace App\Enums;

enum LabelField: string
{
    case MemberNumber = 'member_number';
    case FullName = 'full_name';
    case Address = 'address';
    case PostalCodeCity = 'postal_code_city';
    case Province = 'province';

    /** Posición en la etiqueta (1-based, orden fijo). */
    public function sortOrder(): int
    {
        return match ($this) {
            self::MemberNumber => 1,
            self::FullName => 2,
            self::Address => 3,
            self::PostalCodeCity => 4,
            self::Province => 5,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::MemberNumber => 'Nº de socio',
            self::FullName => 'Nombre completo',
            self::Address => 'Dirección',
            self::PostalCodeCity => 'Código postal y ciudad',
            self::Province => 'Provincia',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case) => [$case->value => $case->label()])
            ->all();
    }

    /** Selección por defecto: nombre, dirección, CP+ciudad. */
    public static function defaultSelection(): array
    {
        return [
            self::FullName->value,
            self::Address->value,
            self::PostalCodeCity->value,
        ];
    }
}
