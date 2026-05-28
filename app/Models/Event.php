<?php

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory;

    protected $fillable = [
        'event_code',
        'name',
        'description',
        'event_type',
        'status',
        'start_date',
        'end_date',
        'registration_deadline',
        'max_companions_per_member',
        'base_price',
        'companion_surcharge',
        'non_member_surcharge',
        'is_active',
        'notes',
        'cancellation_reason',
        'last_registration_sequence',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => EventType::class,
            'status' => EventStatus::class,
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'registration_deadline' => 'date',
            'max_companions_per_member' => 'integer',
            'base_price' => 'decimal:2',
            'companion_surcharge' => 'decimal:2',
            'non_member_surcharge' => 'decimal:2',
            'is_active' => 'boolean',
            'last_registration_sequence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $event): void {
            if (! filled($event->event_code)) {
                $event->event_code = self::generateEventCode($event);
            }

            $event->event_code = Str::upper((string) $event->event_code);
        });

        static::updated(function (self $event): void {
            if (! $event->wasChanged('status')) {
                return;
            }

            if ($event->status !== EventStatus::Cancelado) {
                return;
            }

            $event->cancelActiveRegistrations();
        });
    }

    protected static function generateEventCode(self $event): string
    {
        $prefix = self::eventCodePrefix($event->event_type);
        $year = self::eventCodeYear($event->start_date);
        $sequence = self::nextEventCodeSequence($prefix, $year);

        return sprintf('%s-%s-%04d', $prefix, $year, $sequence);
    }

    protected static function eventCodePrefix(mixed $eventType): string
    {
        $resolvedEventType = $eventType instanceof EventType
            ? $eventType
            : EventType::tryFrom((string) $eventType) ?? EventType::Evento;

        return match ($resolvedEventType) {
            EventType::Venta => 'VNT',
            default => 'EVT',
        };
    }

    protected static function eventCodeYear(mixed $startDate): string
    {
        if ($startDate instanceof \DateTimeInterface) {
            return $startDate->format('y');
        }

        if (filled($startDate)) {
            return Carbon::parse($startDate)->format('y');
        }

        return now()->format('y');
    }

    protected static function nextEventCodeSequence(string $prefix, string $year): int
    {
        $lastEventCode = static::query()
            ->where('event_code', 'like', "{$prefix}-{$year}-%")
            ->orderByDesc('event_code')
            ->value('event_code');

        if (! is_string($lastEventCode)) {
            return 1;
        }

        return ((int) Str::substr($lastEventCode, -4)) + 1;
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }

    /**
     * @return array{amount: float, surcharge_amount: float}
     */
    public function priceFor(RegistrationType $registrationType): array
    {
        return match ($registrationType) {
            RegistrationType::Socio => [
                'amount' => (float) $this->base_price,
                'surcharge_amount' => 0.0,
            ],
            RegistrationType::Acompanante => [
                'amount' => (float) $this->base_price,
                'surcharge_amount' => (float) $this->companion_surcharge,
            ],
            RegistrationType::NoSocio => [
                'amount' => (float) $this->base_price,
                'surcharge_amount' => (float) $this->non_member_surcharge,
            ],
        };
    }

    public function isEditable(): bool
    {
        return $this->status === EventStatus::Abierto && $this->start_date?->isFuture();
    }

    public function canAcceptRegistrations(): bool
    {
        if ($this->status !== EventStatus::Abierto) {
            return false;
        }

        if (! $this->registration_deadline) {
            return true;
        }

        return ! $this->registration_deadline->isPast();
    }

    protected function cancelActiveRegistrations(): void
    {
        DB::transaction(function (): void {
            $this->registrations()
                ->whereIn('status', [
                    RegistrationStatus::Pendiente->value,
                    RegistrationStatus::Confirmada->value,
                ])
                ->get()
                ->each(function (Registration $registration): void {
                    $registration->update([
                        'status' => RegistrationStatus::Cancelada,
                    ]);
                });
        });
    }
}
