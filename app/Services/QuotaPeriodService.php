<?php

namespace App\Services;

use App\Enums\PaymentPeriod;
use App\Models\Association;
use App\Models\Member;
use App\Models\QuotaType;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class QuotaPeriodService
{
    public static function graceDays(QuotaType $quotaType): int
    {
        return max((int) $quotaType->grace_days, 0);
    }

    public static function defaultPeriod(): PaymentPeriod
    {
        $assoc = Association::query()->first();

        return $assoc?->payment_period ?? PaymentPeriod::Anual;
    }

    public static function defaultAmount(QuotaType $quotaType): ?float
    {
        return $quotaType->default_amount !== null ? (float) $quotaType->default_amount : null;
    }

    public static function shouldAlignPeriodStart(QuotaType $quotaType): bool
    {
        return (bool) $quotaType->align_period_start;
    }

    /**
     * @return array{period_start: string, period_end: string, due_date: string}
     */
    public static function forMember(
        Member $member,
        QuotaType $quotaType,
        ?PaymentPeriod $period = null,
        ?CarbonInterface $referenceDate = null,
    ): array {
        $period ??= $quotaType->period_type;
        $referenceDate ??= now();

        $periodStart = self::resolvePeriodStart($member, $period, $referenceDate, $quotaType);
        $periodEnd = (clone $periodStart)->addMonthsNoOverflow($period->months())->subDay();
        $defaultDueDate = (clone $periodStart)->addDays(self::graceDays($quotaType));
        $dueDate = self::resolveDueDate($member, $periodStart, $periodEnd, $defaultDueDate, $quotaType);

        return [
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'due_date' => $dueDate->toDateString(),
        ];
    }

    protected static function resolveDueDate(
        Member $member,
        CarbonInterface $periodStart,
        CarbonInterface $periodEnd,
        CarbonInterface $defaultDueDate,
        QuotaType $quotaType,
    ): CarbonInterface {
        $joinedAt = $member->joined_at?->copy();

        if (! $joinedAt) {
            return $defaultDueDate;
        }

        $joinedWithinCurrentPeriod = $joinedAt->greaterThanOrEqualTo($periodStart)
            && $joinedAt->lessThanOrEqualTo($periodEnd);

        if ($joinedWithinCurrentPeriod) {
            return $joinedAt->copy()->addDays(self::graceDays($quotaType));
        }

        return $defaultDueDate;
    }

    protected static function resolvePeriodStart(
        Member $member,
        PaymentPeriod $period,
        CarbonInterface $referenceDate,
        QuotaType $quotaType,
    ): CarbonInterface {
        if (self::shouldAlignPeriodStart($quotaType)) {
            return match ($period) {
                PaymentPeriod::Mensual => $referenceDate->copy()->startOfMonth(),
                PaymentPeriod::Trimestral => $referenceDate->copy()->startOfQuarter(),
                PaymentPeriod::Semestral => $referenceDate->copy()->month($referenceDate->month <= 6 ? 1 : 7)->startOfMonth(),
                PaymentPeriod::Anual => $referenceDate->copy()->startOfYear(),
            };
        }

        $joinedAt = $member->joined_at?->copy() ?? $referenceDate->copy();
        $year = $referenceDate->year;
        $month = $joinedAt->month;
        $day = min($joinedAt->day, Carbon::create($year, $month, 1)->daysInMonth);

        return Carbon::create($year, $month, $day)->startOfDay();
    }
}
