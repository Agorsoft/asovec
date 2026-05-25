<?php

namespace App\Models;

use App\Enums\EventType;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use Database\Factories\RegistrationFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class Registration extends Model
{
    /** @use HasFactory<RegistrationFactory> */
    use HasFactory;

    protected $fillable = [
        'event_id',
        'member_id',
        'registration_type',
        'sponsor_member_id',
        'registration_sequence',
        'registration_number',
        'status',
        'amount',
        'surcharge_amount',
        'payment_method',
        'paid_at',
        'notes',
    ];

    protected $appends = [
        'total_amount',
    ];

    protected function casts(): array
    {
        return [
            'registration_type' => RegistrationType::class,
            'status' => RegistrationStatus::class,
            'amount' => 'decimal:2',
            'surcharge_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }

    protected function totalAmount(): Attribute
    {
        return Attribute::make(
            get: fn (): string => number_format((float) $this->amount + (float) $this->surcharge_amount, 2, '.', ''),
        );
    }

    protected static function booted(): void
    {
        static::creating(function (self $registration): void {
            $registration->validateRegistrationRules();
            $registration->fillPricingFromEvent();
            $registration->assignEventSequenceAndNumber();

            if ($registration->status === RegistrationStatus::Confirmada && ! filled($registration->paid_at)) {
                $registration->paid_at = now();
            }
        });

        static::created(function (self $registration): void {
            if ($registration->status !== RegistrationStatus::Confirmada) {
                return;
            }

            $registration->syncIncomeTransactions();
        });

        static::updating(function (self $registration): void {
            if (! $registration->isDirty('status')) {
                return;
            }

            if ($registration->status !== RegistrationStatus::Cancelada) {
                return;
            }

            if ($registration->getRawOriginal('status') !== RegistrationStatus::Confirmada->value) {
                return;
            }

            $registration->createRefundTransactions();
        });

        static::updated(function (self $registration): void {
            if (! $registration->wasChanged('status')) {
                return;
            }

            if ($registration->status !== RegistrationStatus::Confirmada) {
                return;
            }

            $registration->syncIncomeTransactions();
        });

        static::deleting(function (self $registration): void {
            $registration->transactions()->delete();
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function sponsorMember(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'sponsor_member_id');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    public function confirm(string $paymentMethod, ?string $notes = null): void
    {
        if ($this->status === RegistrationStatus::Confirmada && $this->hasIncomeTransactions()) {
            return;
        }

        $this->forceFill([
            'status' => RegistrationStatus::Confirmada,
            'paid_at' => now(),
            'payment_method' => $paymentMethod,
            'notes' => $notes ?? $this->notes,
        ])->save();

        $this->syncIncomeTransactions();
    }

    protected function hasIncomeTransactions(): bool
    {
        return $this->transactions()
            ->where('type', TransactionType::Ingreso->value)
            ->whereNull('parent_transaction_id')
            ->exists();
    }

    protected function syncIncomeTransactions(): void
    {
        if ($this->hasIncomeTransactions()) {
            return;
        }

        if ((float) $this->amount + (float) $this->surcharge_amount <= 0) {
            return;
        }

        if (! filled($this->paid_at)) {
            $this->forceFill(['paid_at' => now()])->saveQuietly();
        }

        $paymentMethod = filled($this->payment_method)
            ? (string) $this->payment_method
            : (Association::defaultPaymentMethod() ?: 'Transferencia');

        if (! filled($this->payment_method)) {
            $this->forceFill(['payment_method' => $paymentMethod])->saveQuietly();
        }

        $participant = $this->resolveParticipantIdentifier();
        $eventName = $this->event?->name ?? 'Evento';
        $registrationType = $this->resolveRegistrationType();
        $typeLabel = $registrationType?->getLabel() ?? 'Inscripción';
        $registrationCode = $this->registration_number ?: 'SIN-CODIGO';
        $receiptNumber = $this->registration_number;

        $isEspecial = $this->isVentaEspecial();

        $parentIncome = $this->transactions()->create([
            'type' => TransactionType::Ingreso,
            'amount' => (float) $this->amount + (float) $this->surcharge_amount,
            'date' => now()->toDateString(),
            'description' => "Inscripción {$eventName} · {$participant} · {$registrationCode}",
            'source_type' => $isEspecial ? TransactionSourceType::InscripcionEspecial : TransactionSourceType::InscripcionEvento,
            'payment_method' => $paymentMethod,
            'receipt_number' => $receiptNumber,
            'notes' => $this->notes,
        ]);

        $this->transactions()->create([
            'type' => TransactionType::Ingreso,
            'amount' => $this->amount,
            'date' => now()->toDateString(),
            'description' => "Base {$eventName} · {$participant} · {$registrationCode}",
            'parent_transaction_id' => $parentIncome->id,
            'source_type' => $isEspecial ? TransactionSourceType::BaseEspecial : TransactionSourceType::BaseInscripcionEvento,
            'payment_method' => $paymentMethod,
            'receipt_number' => $receiptNumber,
            'notes' => $this->notes,
        ]);

        if ((float) $this->surcharge_amount <= 0) {
            return;
        }

        $this->transactions()->create([
            'type' => TransactionType::Ingreso,
            'amount' => $this->surcharge_amount,
            'date' => now()->toDateString(),
            'description' => "Recargo {$eventName} · {$participant} · {$registrationCode}",
            'parent_transaction_id' => $parentIncome->id,
            'source_type' => $isEspecial ? TransactionSourceType::RecargoEspecial : TransactionSourceType::RecargoEvento,
            'payment_method' => $paymentMethod,
            'receipt_number' => $receiptNumber,
            'notes' => $this->notes,
        ]);
    }

    protected function validateRegistrationRules(): void
    {
        $event = $this->event()->first();
        $member = $this->member()->first();
        $registrationType = $this->resolveRegistrationType();

        if (! $event || ! $member) {
            throw ValidationException::withMessages([
                'event_id' => 'Evento o participante no válido.',
            ]);
        }

        if (! $registrationType) {
            throw ValidationException::withMessages([
                'registration_type' => 'Selecciona un tipo de inscripción válido.',
            ]);
        }

        if (! $event->canAcceptRegistrations()) {
            throw ValidationException::withMessages([
                'event_id' => 'El evento no admite nuevas inscripciones.',
            ]);
        }

        if ($registrationType === RegistrationType::Socio && $member->member_type !== MemberType::Socio) {
            throw ValidationException::withMessages([
                'registration_type' => 'El tipo de inscripción Socio requiere un socio.',
            ]);
        }

        if ($registrationType === RegistrationType::Socio
            && $member->member_type === MemberType::Socio
            && $member->status === MemberStatus::Pendiente) {
            throw ValidationException::withMessages([
                'registration_type' => 'El socio no puede inscribirse como Socio porque no está al corriente de pago de la cuota.',
            ]);
        }

        $isBajaSocio = $member->member_type === MemberType::Socio
            && $member->status === MemberStatus::Baja;

        $isPendingSocio = $member->member_type === MemberType::Socio
            && $member->status === MemberStatus::Pendiente;

        if ($registrationType === RegistrationType::NoSocio && $isPendingSocio) {
            throw ValidationException::withMessages([
                'member_id' => 'El participante consta como socio pendiente. Deberá darse de baja o continuar con el pago de la cuota.',
            ]);
        }

        if ($registrationType === RegistrationType::NoSocio
            && $member->member_type !== MemberType::NoSocio
            && ! $isBajaSocio) {
            throw ValidationException::withMessages([
                'registration_type' => 'El tipo seleccionado requiere un no socio o un socio dado de baja como participante.',
            ]);
        }

        if ($registrationType === RegistrationType::Acompanante
            && $member->member_type !== MemberType::NoSocio
            && ! $isBajaSocio
            && ! $isPendingSocio) {
            throw ValidationException::withMessages([
                'registration_type' => 'El tipo seleccionado requiere un no socio o un socio no activo como participante.',
            ]);
        }

        $existingRegistration = static::query()
            ->where('event_id', $this->event_id)
            ->where('member_id', $this->member_id)
            ->where('status', '!=', RegistrationStatus::Cancelada->value)
            ->exists();

        if ($existingRegistration) {
            throw ValidationException::withMessages([
                'member_id' => 'Este participante ya está inscrito en el evento.',
            ]);
        }

        if ($registrationType !== RegistrationType::Acompanante) {
            $this->sponsor_member_id = null;

            return;
        }

        if (! filled($this->sponsor_member_id)) {
            throw ValidationException::withMessages([
                'sponsor_member_id' => 'Un acompañante requiere un socio patrocinador.',
            ]);
        }

        $sponsor = Member::query()->find($this->sponsor_member_id);

        if (! $sponsor || $sponsor->member_type !== MemberType::Socio) {
            throw ValidationException::withMessages([
                'sponsor_member_id' => 'El patrocinador debe ser un socio.',
            ]);
        }

        $companionsCount = static::query()
            ->where('event_id', $this->event_id)
            ->where('sponsor_member_id', $this->sponsor_member_id)
            ->where('registration_type', RegistrationType::Acompanante->value)
            ->where('status', '!=', RegistrationStatus::Cancelada->value)
            ->count();

        if ($companionsCount >= (int) $event->max_companions_per_member) {
            throw ValidationException::withMessages([
                'sponsor_member_id' => 'Se alcanzó el número máximo de acompañantes para este socio.',
            ]);
        }
    }

    protected function fillPricingFromEvent(): void
    {
        $event = $this->event()->first();
        $registrationType = $this->resolveRegistrationType();

        if (! $event || ! $registrationType) {
            return;
        }

        $pricing = $event->priceFor($registrationType);

        if (! filled($this->amount)) {
            $this->amount = $pricing['amount'];
        }

        if (! filled($this->surcharge_amount)) {
            $this->surcharge_amount = $pricing['surcharge_amount'];
        }
    }

    protected function assignEventSequenceAndNumber(): void
    {
        if (filled($this->registration_number) && filled($this->registration_sequence)) {
            return;
        }

        DB::transaction(function (): void {
            $event = Event::query()->lockForUpdate()->findOrFail($this->event_id);
            $nextSequence = $event->last_registration_sequence + 1;

            $event->update([
                'last_registration_sequence' => $nextSequence,
            ]);

            $this->registration_sequence = $nextSequence;
            $this->registration_number = sprintf('%s-%04d', $event->event_code, $nextSequence);
        });
    }

    protected function resolveParticipantIdentifier(): string
    {
        $registrationType = $this->resolveRegistrationType();

        if ($registrationType === RegistrationType::Acompanante) {
            $name = $this->member?->full_name ?: 'Acompañante';
            $sponsor = $this->sponsorMember?->member_number_formatted;

            return $sponsor ? "Acompañante {$name} · Socio #{$sponsor}" : "Acompañante {$name}";
        }

        if ($this->member?->member_type === MemberType::Socio) {
            return 'Socio #'.($this->member->member_number_formatted ?: '-');
        }

        return 'No socio '.($this->member?->full_name ?: '-');
    }

    protected function resolveRegistrationType(): ?RegistrationType
    {
        if ($this->registration_type instanceof RegistrationType) {
            return $this->registration_type;
        }

        if (! is_string($this->registration_type)) {
            return null;
        }

        return RegistrationType::tryFrom($this->registration_type);
    }

    protected function createRefundTransactions(): void
    {
        $incomeTransactions = $this->transactions()
            ->where('type', TransactionType::Ingreso->value)
            ->whereNull('parent_transaction_id')
            ->with('childTransactions')
            ->get();

        if ($incomeTransactions->isEmpty()) {
            return;
        }

        foreach ($incomeTransactions as $incomeTransaction) {
            $alreadyReversed = $this->transactions()
                ->where('type', TransactionType::Gasto->value)
                ->whereNull('parent_transaction_id')
                ->where('notes', "Reembolso de transacción #{$incomeTransaction->id}")
                ->exists();

            if ($alreadyReversed) {
                continue;
            }

            $parentExpense = $this->transactions()->create([
                'type' => TransactionType::Gasto,
                'amount' => $incomeTransaction->amount,
                'date' => now()->toDateString(),
                'description' => 'Reembolso '.$incomeTransaction->description,
                'source_type' => $this->refundSourceTypeFor($incomeTransaction),
                'payment_method' => $incomeTransaction->payment_method,
                'receipt_number' => $incomeTransaction->receipt_number,
                'notes' => "Reembolso de transacción #{$incomeTransaction->id}",
            ]);

            foreach ($incomeTransaction->childTransactions as $childIncomeTransaction) {
                $this->transactions()->create([
                    'type' => TransactionType::Gasto,
                    'amount' => $childIncomeTransaction->amount,
                    'date' => now()->toDateString(),
                    'description' => 'Reembolso '.$childIncomeTransaction->description,
                    'parent_transaction_id' => $parentExpense->id,
                    'source_type' => $this->refundSourceTypeFor($childIncomeTransaction),
                    'payment_method' => $childIncomeTransaction->payment_method,
                    'receipt_number' => $childIncomeTransaction->receipt_number,
                    'notes' => "Reembolso de transacción #{$childIncomeTransaction->id}",
                ]);
            }
        }
    }

    protected function isVentaEspecial(): bool
    {
        return ($this->event?->event_type ?? EventType::Evento) === EventType::Venta;
    }

    protected function refundSourceTypeFor(Transaction $transaction): TransactionSourceType
    {
        return match ($transaction->source_type) {
            TransactionSourceType::RecargoEvento => TransactionSourceType::ReembolsoRecargoEvento,
            TransactionSourceType::RecargoEspecial => TransactionSourceType::ReembolsoRecargoEspecial,
            TransactionSourceType::InscripcionEspecial,
            TransactionSourceType::BaseEspecial => TransactionSourceType::ReembolsoEspecial,
            default => TransactionSourceType::ReembolsoInscripcionEvento,
        };
    }
}
