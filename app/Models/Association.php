<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentPeriod;
use Database\Factories\AssociationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

#[Fillable([
    'name',
    'tax_id',
    'registration_entity',
    'registration_number',
    'email',
    'phone',
    'address',
    'postal_code',
    'city',
    'province',
    'country',
    'website',
    'logo_path',
    'payment_methods',
    'observations',
    'payment_period',
    'receipt_number_mask',
    'last_member_number',
    'last_receipt_number',
    'iban',
    'bic',
    'creditor_identifier',
    'bank_name',
    'bank_branch',
    'bank_address',
    'bank_postal_code',
    'bank_city',
    'bank_province',
    'bank_country',
    'installed_at',
])]
class Association extends Model
{
    /** @use HasFactory<AssociationFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_member_number' => 0,
        'last_receipt_number' => 0,
    ];

    /**
     * Obtiene el siguiente número de socio correlativo e incrementa el contador.
     */
    public static function nextMemberNumber(): int
    {
        return DB::transaction(function (): int {
            $association = static::query()->lockForUpdate()->first();

            // If no association row exists, create one and use 1 as the first number
            if (! $association) {
                $association = static::create([
                    'name' => 'Asociación',
                    'last_member_number' => 0,
                    'receipt_number_mask' => '{Y:2}-{N:4}',
                    'last_receipt_number' => 0,
                ]);
            }

            $maxPersistedMemberNumber = (int) Member::query()
                ->selectRaw('COALESCE(MAX(CAST(member_number AS UNSIGNED)), 0) as max_member_number')
                ->value('max_member_number');

            $candidate = max((int) $association->last_member_number, $maxPersistedMemberNumber) + 1;

            // Ensure the candidate is not already used by any Member.
            // If it is, keep incrementing until we find a free number.
            while (Member::query()->where('member_number', (string) $candidate)->exists()) {
                $candidate++;
            }

            // Persist the chosen number as the new last_member_number
            $association->update(['last_member_number' => $candidate]);

            return $candidate;
        });
    }

    /**
     * Obtiene el siguiente número de recibo y actualiza el contador en asociación.
     */
    public static function nextReceiptNumber(): string
    {
        return DB::transaction(function (): string {
            $association = static::query()->lockForUpdate()->first();

            if (! $association) {
                $association = static::create([
                    'name' => 'Asociación',
                    'last_member_number' => 0,
                    'last_receipt_number' => 0,
                    'receipt_number_mask' => '{Y:2}-{N:4}',
                ]);
            }

            $next = (int) $association->last_receipt_number + 1;
            $association->update(['last_receipt_number' => $next]);

            return static::formatReceiptNumber($next, (string) $association->receipt_number_mask);
        });
    }

    /**
     * @return array<string, string>
     */
    public static function paymentMethodOptions(): array
    {
        $association = static::query()->first();

        if (! $association) {
            return collect(PaymentMethod::cases())
                ->mapWithKeys(fn (PaymentMethod $paymentMethod): array => [$paymentMethod->value => $paymentMethod->label()])
                ->all();
        }

        $options = collect($association->payment_methods ?? [])
            ->mapWithKeys(function (mixed $paymentMethod): array {
                if ($paymentMethod instanceof PaymentMethod) {
                    return [$paymentMethod->value => $paymentMethod->label()];
                }

                if (is_string($paymentMethod)) {
                    $enum = PaymentMethod::tryFrom($paymentMethod);

                    return [$paymentMethod => $enum?->label() ?? $paymentMethod];
                }

                return [];
            })
            ->all();

        if ($options !== []) {
            return $options;
        }

        return collect(PaymentMethod::cases())
            ->mapWithKeys(fn (PaymentMethod $paymentMethod): array => [$paymentMethod->value => $paymentMethod->label()])
            ->all();
    }

    public static function defaultPaymentMethod(): ?string
    {
        $options = static::paymentMethodOptions();

        return count($options) === 1 ? array_key_first($options) : null;
    }

    protected static function formatReceiptNumber(int $sequence, string $mask): string
    {
        preg_match('/\{(YYYY|Y:(\d+))\}-\{(NNNN|N:(\d+))\}/', $mask, $matches);

        $yearDigits = (int) ($matches[2] ?? 4);
        $numberDigits = (int) ($matches[4] ?? 4);

        $year = substr((string) now()->year, -$yearDigits);
        $number = str_pad((string) $sequence, max(1, $numberDigits), '0', STR_PAD_LEFT);

        return "{$year}-{$number}";
    }

    /**
     * @return array<string, string|object>
     */
    protected function casts(): array
    {
        return [
            'payment_methods' => AsEnumCollection::of(PaymentMethod::class),
            'payment_period' => PaymentPeriod::class,
            'last_member_number' => 'integer',
            'last_receipt_number' => 'integer',
            'installed_at' => 'datetime',
        ];
    }
}
