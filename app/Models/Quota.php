<?php

namespace App\Models;

use App\Enums\MemberStatus;
use App\Enums\QuotaStatus;
use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Validation\ValidationException;

class Quota extends Model
{
    use HasFactory;

    protected $table = 'quotas';

    protected $fillable = [
        'member_id',
        'quota_type_id',
        'period_start',
        'period_end',
        'amount',
        'status',
        'due_date',
        'paid_at',
        'receipt_number',
        'payment_method',
        'notes',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'amount' => 'decimal:2',
        'status' => QuotaStatus::class,
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $quota): void {
            if (! filled($quota->quota_type_id)) {
                $quota->quota_type_id = QuotaType::query()->oldest('id')->value('id');
            }

            $status = $quota->status instanceof QuotaStatus
                ? $quota->status
                : QuotaStatus::tryFrom((string) $quota->status);

            if ($status === QuotaStatus::Pagada && ! filled($quota->receipt_number)) {
                $quota->receipt_number = Association::nextReceiptNumber();
            }
        });

        static::created(function (self $quota): void {
            $quota->syncMemberStatusFromCurrentPaidCoverage();

            if ($quota->status === QuotaStatus::Pagada) {
                $quota->createIncomeTransaction($quota->payment_method ?: 'Transferencia');
            }
        });

        static::updating(function (self $quota): void {
            // Prevent changing status from a previously cancelled quota.
            if ($quota->getRawOriginal('status') === QuotaStatus::Cancelada->value && $quota->isDirty('status')) {
                throw ValidationException::withMessages([
                    'status' => 'No se puede modificar el estado de una cuota Cancelada; cree una nueva.',
                ]);
            }

            if (! $quota->isDirty('status')) {
                return;
            }

            // Only run cancellation flow when the new status is Cancelada.
            if ($quota->status !== QuotaStatus::Cancelada) {
                return;
            }

            $quota->ensureCanBeCancelled();

            if ($quota->getRawOriginal('status') !== QuotaStatus::Pagada->value) {
                return;
            }

            $quota->createRefundTransactions();
        });

        static::updated(function (self $quota): void {
            if (! $quota->wasChanged('status')) {
                return;
            }

            $previousStatus = $quota->getPrevious()['status'] ?? null;

            if (! in_array($quota->status, [QuotaStatus::Pagada, QuotaStatus::Cancelada], true) && $previousStatus !== QuotaStatus::Pagada->value) {
                return;
            }

            $quota->syncMemberStatusFromCurrentPaidCoverage();
        });

        static::deleting(function (self $quota): void {
            // Prevent deletion of paid or cancelled quotas to preserve accounting integrity.
            if (in_array($quota->status, [QuotaStatus::Pagada, QuotaStatus::Cancelada], true)) {
                throw ValidationException::withMessages([
                    'delete' => 'No se pueden eliminar cuotas con estado Pagada o Cancelada.',
                ]);
            }

            $quota->transactions()->delete();
        });

        static::deleted(function (self $quota): void {
            $quota->syncMemberStatusFromCurrentPaidCoverage();
        });
    }

    // Relationships
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function quotaType(): BelongsTo
    {
        return $this->belongsTo(QuotaType::class, 'quota_type_id');
    }

    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }

    public function markAsPaid(string $paymentMethod, ?string $notes = null): void
    {
        $receiptNumber = $this->receipt_number ?: Association::nextReceiptNumber();

        $this->forceFill([
            'status' => QuotaStatus::Pagada,
            'paid_at' => now(),
            'receipt_number' => $receiptNumber,
            'payment_method' => $paymentMethod,
            'notes' => $notes ?: $this->notes,
        ])->save();

        $this->createIncomeTransaction($paymentMethod, $notes, $receiptNumber);
    }

    public function effectiveStatus(?CarbonInterface $at = null): QuotaStatus
    {
        $at ??= now();

        if ($this->status === QuotaStatus::Pendiente && $this->due_date?->lt($at->copy()->startOfDay())) {
            return QuotaStatus::Vencida;
        }

        return $this->status;
    }

    public function isCurrentPeriod(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if (! $this->period_start || ! $this->period_end) {
            return false;
        }

        return $this->period_start->lessThanOrEqualTo($at->copy()->startOfDay())
            && $this->period_end->greaterThanOrEqualTo($at->copy()->startOfDay());
    }

    public function isPastPeriod(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if (! $this->period_end) {
            return false;
        }

        return $this->period_end->lt($at->copy()->startOfDay());
    }

    public function canBeCancelled(?CarbonInterface $cancelledAt = null): bool
    {
        try {
            $this->ensureCanBeCancelled($cancelledAt);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    public function ensureCanBeCancelled(?CarbonInterface $cancelledAt = null): void
    {
        $cancelledAt ??= now();

        if ($this->isPastPeriod($cancelledAt)) {
            throw ValidationException::withMessages([
                'status' => 'No se pueden cancelar cuotas de periodos anteriores al actual.',
            ]);
        }

        if (! $this->paid_at) {
            return;
        }

        $refundDays = max((int) ($this->quotaType?->refund_days ?? 0), 0);
        $lastCancellationDate = $this->paid_at->copy()->startOfDay()->addDays($refundDays);

        if ($cancelledAt->copy()->startOfDay()->gt($lastCancellationDate)) {
            throw ValidationException::withMessages([
                'status' => 'No se puede cancelar la cuota fuera del plazo de devolución.',
            ]);
        }
    }

    protected function createIncomeTransaction(string $paymentMethod, ?string $notes = null, ?string $receiptNumber = null): void
    {
        $memberNumber = $this->member?->member_number ?? '-';
        $periodLabel = $this->resolveNaturalPeriodLabel();
        $transactionDate = $this->paid_at?->toDateString() ?? now()->toDateString();

        $alreadyExists = $this->transactions()
            ->where('type', TransactionType::Ingreso->value)
            ->where('description', "Cuota Socio #{$memberNumber} · Periodo {$periodLabel}")
            ->exists();

        if ($alreadyExists) {
            return;
        }

        $this->transactions()->create([
            'type' => TransactionType::Ingreso,
            'amount' => $this->amount,
            'date' => $transactionDate,
            'description' => "Cuota Socio #{$memberNumber} · Periodo {$periodLabel}",
            'source_type' => TransactionSourceType::Cuota,
            'payment_method' => $paymentMethod,
            'receipt_number' => $receiptNumber ?: $this->receipt_number,
            'notes' => $notes,
        ]);
    }

    protected function resolveNaturalPeriodLabel(): string
    {
        if (! $this->period_start || ! $this->period_end) {
            return (string) now()->year;
        }

        $isAnnualNaturalPeriod = $this->period_start->copy()->startOfYear()->isSameDay($this->period_start)
            && $this->period_end->copy()->endOfYear()->isSameDay($this->period_end)
            && $this->period_start->year === $this->period_end->year;

        if ($isAnnualNaturalPeriod) {
            return (string) $this->period_start->year;
        }

        return $this->period_start->format('d/m/Y').' - '.$this->period_end->format('d/m/Y');
    }

    protected function createRefundTransactions(): void
    {
        $incomeTransactions = $this->transactions()
            ->where('type', TransactionType::Ingreso->value)
            ->get();

        if ($incomeTransactions->isEmpty()) {
            return;
        }

        $memberNumber = $this->member?->member_number ?? '-';
        $periodLabel = $this->resolveNaturalPeriodLabel();

        foreach ($incomeTransactions as $incomeTransaction) {
            $alreadyReversed = $this->transactions()
                ->where('type', TransactionType::Gasto->value)
                ->where('notes', "Reembolso de transacción #{$incomeTransaction->id}")
                ->exists();

            if ($alreadyReversed) {
                continue;
            }

            $this->transactions()->create([
                'type' => TransactionType::Gasto,
                'amount' => $incomeTransaction->amount,
                'date' => now()->toDateString(),
                'description' => "Devolución Cuota Socio #{$memberNumber} · Periodo {$periodLabel}",
                'source_type' => TransactionSourceType::ReembolsoCuota,
                'payment_method' => $incomeTransaction->payment_method,
                'receipt_number' => $incomeTransaction->receipt_number,
                'notes' => "Reembolso de transacción #{$incomeTransaction->id}",
            ]);
        }
    }

    protected function syncMemberStatusFromCurrentPaidCoverage(): void
    {
        $member = $this->member;

        if (! $member || $member->status === MemberStatus::Baja) {
            return;
        }

        if (static::memberHasCurrentPaidCoverage($member->id)) {
            $updates = [];

            if ($member->status !== MemberStatus::Activo) {
                $updates['status'] = MemberStatus::Activo;
            }

            if (! filled($member->member_number)) {
                $updates['member_number'] = (string) Association::nextMemberNumber();
            }

            if (! $member->joined_at) {
                $updates['joined_at'] = now()->toDateString();
            }

            if ($updates !== []) {
                $member->update($updates);
            }

            return;
        }

        if ($member->status === MemberStatus::Activo) {
            $member->update(['status' => MemberStatus::Pendiente]);
        }
    }

    protected static function memberHasCurrentPaidCoverage(int $memberId, ?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return static::query()
            ->where('member_id', $memberId)
            ->where('status', QuotaStatus::Pagada)
            ->whereDate('period_start', '<=', $at->toDateString())
            ->whereDate('period_end', '>=', $at->toDateString())
            ->exists();
    }

    public static function existsForMemberPeriod(
        int $memberId,
        string $periodStart,
        string $periodEnd,
        ?int $ignoreQuotaId = null,
    ): bool {
        return static::query()
            ->where('member_id', $memberId)
            ->where('status', '!=', QuotaStatus::Cancelada->value)
            ->whereDate('period_start', $periodStart)
            ->whereDate('period_end', $periodEnd)
            ->when($ignoreQuotaId, fn ($query) => $query->whereKeyNot($ignoreQuotaId))
            ->exists();
    }
}
