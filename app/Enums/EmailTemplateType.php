<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum EmailTemplateType: string implements HasColor, HasLabel
{
    case QuotaPaid = 'quota_paid';
    case QuotaPending = 'quota_pending';
    case QuotaExpired = 'quota_expired';
    case Registration = 'registration';
    case Reminder = 'reminder';
    case Birthday = 'birthday';

    public function getLabel(): string
    {
        return match ($this) {
            self::QuotaPaid => 'Cuota pagada (recibo)',
            self::QuotaPending => 'Cuota pendiente (aviso)',
            self::QuotaExpired => 'Cuota vencida',
            self::Registration => 'Inscripción a evento',
            self::Reminder => 'Recordatorio',
            self::Birthday => 'Cumpleaños',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::QuotaPaid => 'success',
            self::QuotaPending => 'warning',
            self::QuotaExpired => 'danger',
            self::Registration => 'info',
            self::Reminder => 'gray',
            self::Birthday => 'primary',
        };
    }

    /**
     * Variables de contexto disponibles para cada tipo.
     *
     * @return array<string>
     */
    public function availableVariableGroups(): array
    {
        return match ($this) {
            self::QuotaPaid,
            self::QuotaPending,
            self::QuotaExpired => ['Asociación', 'Socio', 'Cuota', 'Tipo de Cuota', 'Fecha'],
            self::Registration => ['Asociación', 'Socio', 'Fecha'],
            self::Reminder,
            self::Birthday => ['Asociación', 'Socio', 'Fecha'],
        };
    }
}
