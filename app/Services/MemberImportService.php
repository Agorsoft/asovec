<?php

namespace App\Services;

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPeriod;
use App\Enums\QuotaStatus;
use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class MemberImportService
{
    /**
     * @return array{imported: int, skipped: int, errors: array}
     */
    public static function import(array $rows): array
    {
        // Verify no members exist
        if (Member::count() > 0) {
            throw ValidationException::withMessages([
                'import' => 'Ya existen socios registrados. La importación solo se permite si no hay socios.',
            ]);
        }

        // Verify QuotaTypes exist
        if (QuotaType::query()->count() === 0) {
            throw ValidationException::withMessages([
                'import' => 'No existen Tipos de Cuota. Cree al menos uno antes de importar socios.',
            ]);
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        $seenNumbers = collect();

        foreach ($rows as $index => $row) {
            try {
                $validated = static::validateRow($row, $index, $seenNumbers);

                if ($validated === null) {
                    $skipped++;

                    continue;
                }

                static::createMemberWithQuota($validated);
                $seenNumbers->push($validated['member_number']);
                $imported++;
            } catch (\Exception $e) {
                $errors[] = [
                    'row' => $index + 2, // +2 because row 1 is header, row 2 is first data
                    'error' => $e->getMessage(),
                ];
            }
        }

        if ($imported > 0) {
            static::syncAssociationLastMemberNumber();
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    /**
     * @param  Collection<int, string>  $seenNumbers
     */
    private static function validateRow(array $row, int $index, Collection $seenNumbers): ?array
    {
        $memberNumber = trim($row['member_number'] ?? '');
        $firstName = trim($row['first_name'] ?? '');
        $lastName = trim($row['last_name'] ?? '');

        // Skip empty rows
        if (! filled($memberNumber) && ! filled($firstName) && ! filled($lastName)) {
            return null;
        }

        // Validate obligatory fields
        if (! filled($memberNumber)) {
            throw new \InvalidArgumentException('Número de socio es obligatorio.');
        }

        if (! ctype_digit($memberNumber)) {
            throw new \InvalidArgumentException("Número de socio inválido: {$memberNumber}. Debe contener solo números.");
        }

        if (! filled($firstName)) {
            throw new \InvalidArgumentException('Nombre es obligatorio.');
        }

        if (! filled($lastName)) {
            throw new \InvalidArgumentException('Apellido es obligatorio.');
        }

        // Validate uniqueness
        if (Member::where('member_number', $memberNumber)->exists()) {
            throw new \InvalidArgumentException("Número de socio {$memberNumber} ya existe.");
        }

        if ($seenNumbers->contains($memberNumber)) {
            throw new \InvalidArgumentException("Número de socio {$memberNumber} duplicado en importación.");
        }

        // Gender validation
        $genderValue = trim($row['gender'] ?? 'NoDefinido');
        $gender = Gender::tryFrom($genderValue) ?? Gender::NoDefinido;

        // Email optional validation
        $email = filled($row['email'] ?? null) ? trim($row['email']) : null;
        if (filled($email) && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException("Email inválido: {$email}");
        }

        // Birth date validation
        $birthDate = null;
        if (filled($row['birth_date'] ?? null)) {
            try {
                $birthDate = static::parseImportDate(trim($row['birth_date']));
            } catch (\Exception) {
                throw new \InvalidArgumentException("Formato de fecha de nacimiento inválido: {$row['birth_date']}. Use DD/MM/YYYY o YYYY-MM-DD.");
            }
        }

        // Payment method validation
        $paymentMethod = null;
        if (filled($row['payment_method'] ?? null)) {
            $methodValue = trim($row['payment_method']);
            $paymentMethod = PaymentMethod::tryFrom($methodValue);
            if (! $paymentMethod) {
                throw new \InvalidArgumentException("Método de pago inválido: {$methodValue}");
            }
        }

        // Joined at date validation
        $joinedAt = null;
        if (filled($row['joined_at'] ?? null)) {
            try {
                $joinedAt = static::parseImportDate(trim($row['joined_at']));
            } catch (\Exception) {
                throw new \InvalidArgumentException("Formato de fecha de alta inválido: {$row['joined_at']}. Use DD/MM/YYYY o YYYY-MM-DD.");
            }
        }

        // Parse quota data if present
        $quotaData = null;
        if (filled($row['last_paid_date'] ?? null)) {
            $quotaData = static::parseQuotaData($row);
        }

        // Determine member status and joined_at
        $memberStatus = MemberStatus::Pendiente;
        if ($quotaData) {
            $memberStatus = MemberStatus::Activo;
            if (! $joinedAt) {
                $joinedAt = $quotaData['paid_date'];
            }
        } elseif (! $joinedAt) {
            $joinedAt = now();
        }

        return [
            'member_number' => $memberNumber,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'dni' => filled($row['dni'] ?? null) ? trim($row['dni']) : null,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'address' => filled($row['address'] ?? null) ? trim($row['address']) : null,
            'postal_code' => filled($row['postal_code'] ?? null) ? trim($row['postal_code']) : null,
            'city' => filled($row['city'] ?? null) ? trim($row['city']) : null,
            'province' => filled($row['province'] ?? null) ? trim($row['province']) : null,
            'phone' => filled($row['phone'] ?? null) ? trim($row['phone']) : null,
            'email' => $email,
            'payment_method_id' => $paymentMethod,
            'joined_at' => $joinedAt,
            'notes' => filled($row['notes'] ?? null) ? trim($row['notes']) : null,
            'status' => $memberStatus,
            'quota_data' => $quotaData,
        ];
    }

    private static function parseQuotaData(array $row): ?array
    {
        $paidDate = trim($row['last_paid_date'] ?? '');

        if (! filled($paidDate)) {
            return null;
        }

        try {
            $date = static::parseImportDate($paidDate);
        } catch (\Exception) {
            throw new \InvalidArgumentException("Formato de fecha de última cuota inválido: {$paidDate}. Use DD/MM/YYYY o YYYY-MM-DD.");
        }

        $quotaTypeName = filled($row['quota_type_name'] ?? null) ? trim($row['quota_type_name']) : null;
        $customAmount = filled($row['custom_amount'] ?? null) ? trim($row['custom_amount']) : null;
        $periodType = filled($row['period_type'] ?? null) ? trim($row['period_type']) : null;

        return [
            'paid_date' => $date,
            'quota_type_name' => $quotaTypeName,
            'custom_amount' => $customAmount ? (float) $customAmount : null,
            'period_type' => $periodType,
        ];
    }

    private static function createMemberWithQuota(array $validated): void
    {
        $member = Member::create([
            'member_number' => $validated['member_number'],
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'dni' => $validated['dni'],
            'gender' => $validated['gender'],
            'birth_date' => $validated['birth_date'],
            'address' => $validated['address'],
            'postal_code' => $validated['postal_code'],
            'city' => $validated['city'],
            'province' => $validated['province'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'payment_method_id' => $validated['payment_method_id'],
            'member_type' => MemberType::Socio,
            'status' => $validated['status']->value,
            'joined_at' => $validated['joined_at'],
            'notes' => $validated['notes'],
        ]);

        if ($validated['quota_data']) {
            static::createQuotaForMember($member, $validated['quota_data']);
        }
    }

    /**
     * Find QuotaType based on import data.
     */
    private static function findQuotaType(array $quotaData): ?QuotaType
    {
        // Priority 1: Match by exact name
        if ($quotaData['quota_type_name']) {
            $quotaType = QuotaType::where('name', $quotaData['quota_type_name'])->first();

            if ($quotaType) {
                return $quotaType;
            }
        }

        // Priority 2: Match by period_type (first by sort_order)
        if ($quotaData['period_type']) {
            $periodEnum = PaymentPeriod::tryFrom($quotaData['period_type']);
            if ($periodEnum) {
                $quotaType = QuotaType::where('period_type', $periodEnum)
                    ->orderBy('sort_order')
                    ->first();

                if ($quotaType) {
                    return $quotaType;
                }
            }
        }

        // Priority 3: Default to first QuotaType (by sort_order)
        return QuotaType::orderBy('sort_order')->first();
    }

    private static function createQuotaForMember(Member $member, array $quotaData): void
    {
        // Find QuotaType
        $quotaType = static::findQuotaType($quotaData);

        if (! $quotaType) {
            throw new \Exception('No se pudo determinar el Tipo de Cuota para el período especificado.');
        }

        // Calculate period based on period_type or quota_type's period_type
        $periodType = $quotaData['period_type']
            ? PaymentPeriod::tryFrom($quotaData['period_type'])
            : $quotaType->period_type;

        if (! $periodType instanceof PaymentPeriod) {
            throw new \Exception('Período de cuota inválido.');
        }

        $paidDate = $quotaData['paid_date'];
        [$periodStart, $periodEnd] = static::calculatePeriod($paidDate, $periodType, $quotaType);

        $amount = $quotaData['custom_amount'] ?? (float) $quotaType->default_amount;

        Quota::create([
            'member_id' => $member->id,
            'quota_type_id' => $quotaType->id,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'amount' => $amount,
            'status' => QuotaStatus::Pagada,
            'paid_at' => $paidDate,
            'payment_method' => Association::defaultPaymentMethod() ?: 'Transferencia',
            'receipt_number' => Association::nextReceiptNumber(),
        ]);
    }

    /**
     * Calculate quota period based on payment period type and reference date.
     */
    private static function calculatePeriod(
        Carbon $referenceDate,
        PaymentPeriod $period,
        QuotaType $quotaType,
    ): array {
        $start = $referenceDate->copy()->startOfMonth();
        $end = $referenceDate->copy()->endOfMonth();

        match ($period) {
            PaymentPeriod::Anual => [
                $start = $referenceDate->copy()->startOfYear(),
                $end = $referenceDate->copy()->endOfYear(),
            ],
            PaymentPeriod::Semestral => [
                $start = $referenceDate->copy()->month <= 6
                    ? $referenceDate->copy()->startOfYear()
                    : $referenceDate->copy()->copy()->month(7)->startOfMonth(),
                $end = $referenceDate->copy()->month <= 6
                    ? $referenceDate->copy()->month(6)->endOfMonth()
                    : $referenceDate->copy()->endOfYear(),
            ],
            PaymentPeriod::Trimestral => [
                $quarter = (int) ceil($referenceDate->month / 3),
                $start = $referenceDate->copy()->month(($quarter - 1) * 3 + 1)->startOfMonth(),
                $end = $referenceDate->copy()->month($quarter * 3)->endOfMonth(),
            ],
            PaymentPeriod::Mensual => [
                $start = $referenceDate->copy()->startOfMonth(),
                $end = $referenceDate->copy()->endOfMonth(),
            ],
        };

        return [$start, $end];
    }

    /**
     * Generate example CSV with all 19 columns.
     */
    public static function generateExampleCsv(): string
    {
        $headers = [
            'NUMERO', 'NOMBRE', 'APELLIDOS', 'DNI', 'GENERO', 'FECHA_NACIMIENTO',
            'DIRECCION', 'CODIGO_POSTAL', 'LOCALIDAD', 'PROVINCIA', 'TELEFONO', 'EMAIL',
            'METODO_PAGO', 'FECHA_ALTA', 'FECHA_ULTIMA_CUOTA', 'TIPO_PERIODO',
            'NOMBRE_TIPO_CUOTA', 'IMPORTE_PERSONALIZADO', 'NOTAS',
        ];

        $examples = [
            [
                '0001', 'Juan', 'García López', '12345678A', 'Hombre', '15/06/1980',
                'Calle Principal 123', '28001', 'Madrid', 'Madrid', '912345678', 'juan@example.com',
                'Transferencia', '10/05/2026', '13/05/2026', 'Anual', '', '', 'Ejemplo 1',
            ],
            [
                '0002', 'María', 'Rodríguez López', '87654321B', 'Mujer', '22/03/1985',
                'Avenida Central 456', '08002', 'Barcelona', 'Barcelona', '932345678', 'maria@example.com',
                'Efectivo', '12/05/2026', '30/05/2026', 'Semestral', 'Cuota Especial', '150.00', '',
            ],
            [
                '0003', 'Carlos', 'Martínez Pérez', '11223344C', '', '05/09/1990',
                'Plaza Mayor 789', '46001', 'Valencia', 'Valencia', '', 'carlos@example.com',
                'Domiciliación', '15/05/2026', '', '', '', '', 'Ejemplo 3',
            ],
        ];

        $csv = '"'.implode('","', $headers).'"'.PHP_EOL;
        foreach ($examples as $example) {
            $csv .= '"'.implode('","', $example).'"'.PHP_EOL;
        }

        return $csv;
    }

    private static function parseImportDate(string $value): Carbon
    {
        $formats = ['d/m/Y', 'Y-m-d'];

        foreach ($formats as $format) {
            try {
                $date = Carbon::createFromFormat($format, $value);

                if ($date && $date->format($format) === $value) {
                    return $date;
                }
            } catch (\Exception) {
                // Try next supported format.
            }
        }

        throw new \InvalidArgumentException("Formato de fecha inválido: {$value}");
    }

    private static function syncAssociationLastMemberNumber(): void
    {
        $maxMemberNumber = (int) Member::query()
            ->selectRaw('COALESCE(MAX(CAST(member_number AS UNSIGNED)), 0) as max_member_number')
            ->value('max_member_number');

        if ($maxMemberNumber <= 0) {
            return;
        }

        $association = Association::query()->first();

        if (! $association) {
            Association::query()->create([
                'name' => 'Asociación',
                'last_member_number' => $maxMemberNumber,
                'receipt_number_mask' => '{Y:2}-{N:4}',
                'last_receipt_number' => 0,
            ]);

            return;
        }

        if ((int) $association->last_member_number < $maxMemberNumber) {
            $association->update(['last_member_number' => $maxMemberNumber]);
        }
    }
}
