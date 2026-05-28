<?php

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\QuotaStatus;
use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use App\Services\MemberImportService;
use Illuminate\Validation\ValidationException;

describe('MemberImportService', function () {
    beforeEach(function () {
        Member::query()->delete();
        QuotaType::query()->delete();
        Association::query()->delete();
    });

    describe('import', function () {
        it('rejects import if members already exist', function () {
            Member::factory()->create();

            expect(fn () => MemberImportService::import([]))->toThrow(ValidationException::class);
        });

        it('rejects import if no active quota types exist', function () {
            expect(fn () => MemberImportService::import([]))->toThrow(ValidationException::class);
        });

        it('imports valid members with new extended fields', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '12345678A',
                    'gender' => 'Hombre',
                    'birth_date' => '1980-05-15',
                    'address' => 'Calle Mayor 10',
                    'postal_code' => '28001',
                    'city' => 'Madrid',
                    'province' => 'Madrid',
                    'phone' => '555-1234',
                    'email' => 'juan@example.com',
                    'payment_method' => 'Transferencia',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['imported'])->toBe(1)
                ->and($result['errors'])->toBeEmpty()
                ->and(Member::count())->toBe(1);

            $member = Member::first();
            expect($member->member_number)->toBe('0001')
                ->and($member->first_name)->toBe('Juan')
                ->and($member->last_name)->toBe('García')
                ->and($member->dni)->toBe('12345678A')
                ->and($member->gender)->toBe(Gender::Hombre)
                ->and($member->birth_date->format('Y-m-d'))->toBe('1980-05-15')
                ->and($member->address)->toBe('Calle Mayor 10')
                ->and($member->postal_code)->toBe('28001')
                ->and($member->city)->toBe('Madrid')
                ->and($member->province)->toBe('Madrid')
                ->and($member->phone)->toBe('555-1234')
                ->and($member->email)->toBe('juan@example.com')
                ->and($member->payment_method_id)->toBe(PaymentMethod::Transferencia)
                ->and($member->status)->toBe(MemberStatus::Pendiente);

            $quota = Quota::query()->where('member_id', $member->id)->first();
            expect($quota)->not()->toBeNull()
                ->and($quota->status)->toBe(QuotaStatus::Pendiente)
                ->and($quota->due_date)->not()->toBeNull();
        });

        it('sets member status to Activo when last_paid_date is provided', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '2026-05-13',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            MemberImportService::import($rows);

            $member = Member::first();
            expect($member->status)->toBe(MemberStatus::Activo)
                ->and($member->joined_at->format('Y-m-d'))->toBe('2026-05-13');
        });

        it('skips empty rows', function () {
            QuotaType::factory()->create();

            $rows = [
                ['member_number' => '', 'first_name' => '', 'last_name' => ''],
            ];

            $result = MemberImportService::import($rows);

            expect($result['skipped'])->toBe(1)
                ->and($result['imported'])->toBe(0)
                ->and(Member::count())->toBe(0);
        });

        it('validates required fields', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => '',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['errors'])->toHaveCount(1)
                ->and($result['errors'][0]['row'])->toBe(2)
                ->and($result['errors'][0]['error'])->toContain('Nombre es obligatorio');
        });

        it('rejects invalid email format', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => 'invalid-email',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['errors'])->toHaveCount(1)
                ->and($result['errors'][0]['error'])->toContain('Email inválido');
        });

        it('rejects invalid birth_date format', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '15-05-1980',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['errors'])->toHaveCount(1)
                ->and($result['errors'][0]['error'])->toContain('Formato de fecha de nacimiento inválido');
        });

        it('rejects invalid payment_method', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => 'InvalidMethod',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['errors'])->toHaveCount(1)
                ->and($result['errors'][0]['error'])->toContain('Método de pago inválido');
        });

        it('rejects non numeric member_number', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => 'SOC-001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['errors'])->toHaveCount(1)
                ->and($result['errors'][0]['error'])->toContain('Debe contener solo números');
        });

        it('detects duplicate member numbers in file', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
                [
                    'member_number' => '0001',
                    'first_name' => 'Carlos',
                    'last_name' => 'López',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['imported'])->toBe(1)
                ->and($result['errors'])->toHaveCount(1)
                ->and($result['errors'][0]['error'])->toContain('0001');
        });

        it('creates quotas when last_paid_date is provided', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '2026-05-13',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '100.00',
                    'notes' => 'Test',
                ],
            ];

            MemberImportService::import($rows);

            expect(Member::count())->toBe(1)
                ->and(Quota::count())->toBe(1);

            $quota = Quota::first();
            expect($quota->amount)->toEqual(100.0)
                ->and($quota->status)->toBe(QuotaStatus::Pagada);
        });

        it('creates pending quota for member without last_paid_date', function () {
            QuotaType::factory()->create([
                'period_type' => 'Anual',
                'default_amount' => 50.0,
                'grace_days' => 10,
                'align_period_start' => true,
            ]);

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Pedro',
                    'last_name' => 'Gómez',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            MemberImportService::import($rows);

            $member = Member::first();
            expect($member->status)->toBe(MemberStatus::Pendiente);

            $quota = Quota::query()->where('member_id', $member->id)->first();
            expect($quota)->not()->toBeNull()
                ->and($quota->status)->toBe(QuotaStatus::Pendiente)
                ->and($quota->due_date)->not()->toBeNull()
                ->and((float) $quota->amount)->toBe(50.0);
        });

        it('finds quota type by name', function () {
            $quotaType = QuotaType::factory()->create(['name' => 'CUOTA ANUAL ESPECIAL']);

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '2026-05-13',
                    'period_type' => '',
                    'quota_type_name' => 'CUOTA ANUAL ESPECIAL',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            MemberImportService::import($rows);

            $quota = Quota::first();
            expect($quota->quota_type_id)->toBe($quotaType->id);
        });

        it('uses custom amount for quota when provided', function () {
            QuotaType::factory()->create(['default_amount' => 50.0]);

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '2026-05-13',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '100.50',
                    'notes' => '',
                ],
            ];

            MemberImportService::import($rows);

            $quota = Quota::first();
            expect($quota->amount)->toEqual(100.50);
        });

        it('handles invalid date format for last_paid_date', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '31-12-2024',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['errors'])->toHaveCount(1)
                ->and($result['errors'][0]['error'])->toContain('Formato de fecha de última cuota inválido');
        });

        it('accepts spanish date format dd/mm/YYYY', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '15/05/1980',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '10/05/2026',
                    'last_paid_date' => '13/05/2026',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['imported'])->toBe(1)
                ->and($result['errors'])->toBeEmpty()
                ->and(Member::count())->toBe(1)
                ->and(Quota::count())->toBe(1);

            $member = Member::first();
            expect($member->birth_date->format('Y-m-d'))->toBe('1980-05-15')
                ->and($member->joined_at->format('Y-m-d'))->toBe('2026-05-10')
                ->and($member->status)->toBe(MemberStatus::Activo);

            $quota = Quota::first();
            expect($quota->paid_at->format('Y-m-d'))->toBe('2026-05-13');
        });

        it('imports multiple valid members with mixed payment status', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '0001',
                    'first_name' => 'Juan',
                    'last_name' => 'García',
                    'dni' => '12345678A',
                    'gender' => 'Hombre',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '555-1111',
                    'email' => 'juan@example.com',
                    'payment_method' => 'Transferencia',
                    'joined_at' => '',
                    'last_paid_date' => '2026-05-13',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
                [
                    'member_number' => '0002',
                    'first_name' => 'María',
                    'last_name' => 'López',
                    'dni' => '',
                    'gender' => 'Mujer',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '555-2222',
                    'email' => 'maria@example.com',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
                [
                    'member_number' => '0003',
                    'first_name' => 'Carlos',
                    'last_name' => 'Martínez',
                    'dni' => '',
                    'gender' => 'NoDefinido',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['imported'])->toBe(3)
                ->and($result['errors'])->toBeEmpty()
                ->and(Member::count())->toBe(3)
                ->and(Quota::count())->toBe(3);

            $juan = Member::where('member_number', '0001')->first();
            expect($juan->status)->toBe(MemberStatus::Activo)
                ->and($juan->payment_method_id)->toBe(PaymentMethod::Transferencia)
                ->and($juan->dni)->toBe('12345678A');

            $juanQuota = Quota::where('member_id', $juan->id)->first();
            expect($juanQuota->status)->toBe(QuotaStatus::Pagada);

            $maria = Member::where('member_number', '0002')->first();
            expect($maria->status)->toBe(MemberStatus::Pendiente)
                ->and($maria->payment_method_id)->toBeNull();

            $mariaQuota = Quota::where('member_id', $maria->id)->first();
            expect($mariaQuota->status)->toBe(QuotaStatus::Pendiente);

            $carlos = Member::where('member_number', '0003')->first();
            expect($carlos->status)->toBe(MemberStatus::Pendiente)
                ->and($carlos->gender)->toBe(Gender::NoDefinido);

            $carlosQuota = Quota::where('member_id', $carlos->id)->first();
            expect($carlosQuota->status)->toBe(QuotaStatus::Pendiente);
        });

        it('syncs association member counter with the highest imported member_number', function () {
            QuotaType::factory()->create();

            $rows = [
                [
                    'member_number' => '220',
                    'first_name' => 'Ana',
                    'last_name' => 'Uno',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
                [
                    'member_number' => '223',
                    'first_name' => 'Luis',
                    'last_name' => 'Dos',
                    'dni' => '',
                    'gender' => '',
                    'birth_date' => '',
                    'address' => '',
                    'postal_code' => '',
                    'city' => '',
                    'province' => '',
                    'phone' => '',
                    'email' => '',
                    'payment_method' => '',
                    'joined_at' => '',
                    'last_paid_date' => '',
                    'period_type' => '',
                    'quota_type_name' => '',
                    'custom_amount' => '',
                    'notes' => '',
                ],
            ];

            $result = MemberImportService::import($rows);

            expect($result['imported'])->toBe(2)
                ->and($result['errors'])->toBeEmpty();

            $association = Association::query()->first();
            expect($association)->not()->toBeNull()
                ->and($association->last_member_number)->toBe(223)
                ->and(Association::nextMemberNumber())->toBe(224);
        });
    });

    describe('generateExampleCsv', function () {
        it('generates valid CSV with extended headers and examples', function () {
            $csv = MemberImportService::generateExampleCsv();

            expect($csv)->toContain('NUMERO')
                ->and($csv)->toContain('NOMBRE')
                ->and($csv)->toContain('APELLIDOS')
                ->and($csv)->toContain('DNI')
                ->and($csv)->toContain('FECHA_NACIMIENTO')
                ->and($csv)->toContain('METODO_PAGO')
                ->and($csv)->toContain('FECHA_ULTIMA_CUOTA')
                ->and($csv)->toContain('NOMBRE_TIPO_CUOTA')
                ->and($csv)->toContain('IMPORTE_PERSONALIZADO')
                ->and($csv)->toContain('NOTAS')
                ->and($csv)->toContain('0001')
                ->and($csv)->toContain('Juan');
        });

        it('generates properly formatted CSV lines', function () {
            $csv = MemberImportService::generateExampleCsv();
            $lines = explode("\n", $csv);

            expect(count($lines))->toBeGreaterThanOrEqual(4);
        });
    });
});
