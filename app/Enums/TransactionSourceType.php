<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TransactionSourceType: string implements HasLabel
{
    case Manual = 'Manual';
    case Cuota = 'Cuota';
    case ReembolsoCuota = 'Reembolso Cuota';
    case InscripcionEspecial = 'Especial';
    case BaseEspecial = 'Base Especial';
    case RecargoEspecial = 'Recargo Especial';
    case ReembolsoEspecial = 'Reembolso Especial';
    case ReembolsoRecargoEspecial = 'Reembolso Recargo Especial';
    case InscripcionEvento = 'Inscripción';
    case BaseInscripcionEvento = 'Base';
    case RecargoEvento = 'Recargo';
    case ReembolsoInscripcionEvento = 'Reembolso Inscripción';
    case ReembolsoRecargoEvento = 'Reembolso Recargo';

    /**
     * @return array<int, self>
     */
    public static function basePriceCases(): array
    {
        return [
            self::BaseEspecial,
            self::BaseInscripcionEvento,
        ];
    }

    /**
     * @return array<int, self>
     */
    public static function surchargeCases(): array
    {
        return [
            self::RecargoEspecial,
            self::RecargoEvento,
        ];
    }

    public function isBasePrice(): bool
    {
        return in_array($this, self::basePriceCases(), true);
    }

    public function isSurcharge(): bool
    {
        return in_array($this, self::surchargeCases(), true);
    }

    public function getLabel(): string
    {
        return $this->value;
    }
}
