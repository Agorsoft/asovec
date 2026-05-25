<?php

namespace App\Services;

use App\Enums\PaymentPeriod;
use App\Models\QuotaType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

class QuotaTypeResolver
{
    public static function options(?int $currentQuotaTypeId = null): array
    {
        return self::available($currentQuotaTypeId)
            ->mapWithKeys(fn (QuotaType $t): array => [$t->id => $t->name])
            ->all();
    }

    public static function available(?int $currentQuotaTypeId = null): Collection
    {
        $defaultPeriod = QuotaPeriodService::defaultPeriod();
        $thresholdDate = self::thresholdDate($defaultPeriod);

        return QuotaType::query()
            ->where(function ($query) use ($defaultPeriod, $thresholdDate): void {
                $query
                    ->where('period_type', $defaultPeriod->value)
                    ->whereDate('period_reference_date', '>=', $thresholdDate);
            })
            ->when($currentQuotaTypeId, fn ($query) => $query->orWhere('id', $currentQuotaTypeId))
            ->orderBy('period_reference_date')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    public static function defaultQuotaTypeId(?int $currentQuotaTypeId = null): ?int
    {
        $available = self::available($currentQuotaTypeId);

        if ($currentQuotaTypeId && $available->contains('id', $currentQuotaTypeId)) {
            return $currentQuotaTypeId;
        }

        if ($available->count() === 1) {
            return $available->first()?->id;
        }

        return null;
    }

    public static function resolve(?int $quotaTypeId): ?QuotaType
    {
        if (! $quotaTypeId) {
            return null;
        }

        return QuotaType::query()->find($quotaTypeId);
    }

    public static function hasAvailableTypes(?int $currentQuotaTypeId = null): bool
    {
        return self::available($currentQuotaTypeId)->isNotEmpty();
    }

    public static function thresholdDate(?PaymentPeriod $period = null): string
    {
        $period ??= QuotaPeriodService::defaultPeriod();
        $now = CarbonImmutable::now();

        return match ($period) {
            PaymentPeriod::Mensual => $now->startOfMonth()->toDateString(),
            PaymentPeriod::Trimestral => $now->startOfQuarter()->toDateString(),
            PaymentPeriod::Semestral => $now->month($now->month <= 6 ? 1 : 7)->startOfMonth()->toDateString(),
            PaymentPeriod::Anual => $now->startOfYear()->toDateString(),
        };
    }
}
