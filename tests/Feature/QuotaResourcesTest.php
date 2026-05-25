<?php

use App\Enums\MemberStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPeriod;
use App\Enums\QuotaStatus;
use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use App\Filament\Resources\Quotas\Pages\CreateQuota;
use App\Filament\Resources\Quotas\Pages\EditQuota;
use App\Filament\Resources\Quotas\Pages\ListQuotas;
use App\Filament\Resources\Quotas\Schemas\QuotaForm;
use App\Filament\Resources\QuotaTypes\Pages\CreateQuotaType;
use App\Filament\Resources\QuotaTypes\QuotaTypeResource;
use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('se puede crear un tipo de cuota desde el CRUD', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateQuotaType::class)
        ->fillForm([
            'name' => 'Cuota anual ordinaria',
            'period_type' => PaymentPeriod::Anual->value,
            'default_amount' => 60,
            'grace_days' => 10,
            'sort_order' => 1,
            'align_period_start' => true,
            'description' => 'Cuota anual por defecto para socios.',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $quotaType = QuotaType::query()->first();

    expect($quotaType)->not()->toBeNull()
        ->and($quotaType->name)->toBe('Cuota anual ordinaria')
        ->and($quotaType->period_type)->toEqual(PaymentPeriod::Anual)
        ->and((float) $quotaType->default_amount)->toBe(60.0);
});

test('se puede crear una cuota desde el CRUD', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $member = Member::query()->create([
        'member_number' => '1001',
        'first_name' => 'Laura',
        'last_name' => 'Perez',
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Cuota trimestral',
        'period_type' => PaymentPeriod::Trimestral->value,
        'default_amount' => 25,
        'grace_days' => 5,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    Livewire::test(CreateQuota::class)
        ->fillForm([
            'member_id' => $member->id,
            'quota_type_id' => $quotaType->id,
            'status' => QuotaStatus::Pendiente->value,
            'period_start' => '2026-01-01',
            'period_end' => '2026-03-31',
            'amount' => 25,
            'notes' => 'Cuota del primer trimestre',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $quota = Quota::query()->first();

    expect($quota)->not()->toBeNull()
        ->and($quota->member_id)->toBe($member->id)
        ->and($quota->quota_type_id)->toBe($quotaType->id)
        ->and($quota->status)->toEqual(QuotaStatus::Pendiente)
        ->and($quota->due_date?->toDateString())->toBe(now()->addDays(5)->toDateString())
        ->and((float) $quota->amount)->toBe(25.0);
});

test('las opciones de estado cambian entre crear y editar cuota', function () {
    expect(QuotaForm::statusOptions('create'))->toBe([
        QuotaStatus::Pendiente->value => QuotaStatus::Pendiente->label(),
        QuotaStatus::Pagada->value => QuotaStatus::Pagada->label(),
    ])->and(QuotaForm::statusOptions('edit'))->toBe([
        QuotaStatus::Pendiente->value => QuotaStatus::Pendiente->label(),
        QuotaStatus::Pagada->value => QuotaStatus::Pagada->label(),
        QuotaStatus::Vencida->value => QuotaStatus::Vencida->label(),
        QuotaStatus::Cancelada->value => QuotaStatus::Cancelada->label(),
    ]);
});

test('un tipo de cuota en uso se puede editar pero no borrar', function () {
    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo en uso',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 10,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '2001',
        'first_name' => 'Mario',
        'last_name' => 'Ruiz',
    ]);

    Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'amount' => 10,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    expect(QuotaTypeResource::canEdit($quotaType))->toBeTrue()
        ->and(QuotaTypeResource::canDelete($quotaType))->toBeFalse();
});

test('se puede borrar un tipo de cuota sin cuotas asociadas', function () {
    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo libre',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 10,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    expect(QuotaTypeResource::canDelete($quotaType))->toBeTrue();
});

test('al crear cuota pendiente se autocompleta tipo sin recibo ni activacion de socio', function () {
    Association::factory()->create([
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_receipt_number' => 0,
        'payment_methods' => [PaymentMethod::Transferencia],
    ]);

    $defaultQuotaType = QuotaType::query()->create([
        'name' => 'Tipo por defecto',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 10,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3001',
        'first_name' => 'Eva',
        'last_name' => 'Sanz',
        'status' => MemberStatus::Pendiente->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'amount' => 10,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $member->refresh();
    $association = Association::query()->first();

    expect($quota->quota_type_id)->toBe($defaultQuotaType->id)
        ->and($quota->receipt_number)->toBeNull()
        ->and($member->status)->toEqual(MemberStatus::Pendiente)
        ->and($association?->last_receipt_number)->toBe(0);
});

test('markAsPaid marca cuota como pagada y activa el socio', function () {
    Association::factory()->create([
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_receipt_number' => 0,
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3002',
        'first_name' => 'Luis',
        'last_name' => 'Nuñez',
        'status' => MemberStatus::Pendiente->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $quota->update(['status' => QuotaStatus::Vencida->value]);
    $member->update(['status' => MemberStatus::Pendiente->value]);

    $quota->markAsPaid('Transferencia', 'Pago manual');

    $quota->refresh();
    $member->refresh();

    expect($quota->status)->toEqual(QuotaStatus::Pagada)
        ->and($quota->paid_at)->not()->toBeNull()
        ->and($quota->payment_method)->toBe('Transferencia')
        ->and($quota->receipt_number)->not()->toBeNull()
        ->and($member->status)->toEqual(MemberStatus::Activo)
        ->and($member->joined_at)->not()->toBeNull();

    $incomeTransaction = Transaction::query()->first();
    expect($incomeTransaction)->not()->toBeNull()
        ->and($incomeTransaction->type)->toEqual(TransactionType::Ingreso)
        ->and($incomeTransaction->date?->toDateString())->toBe($quota->paid_at?->toDateString())
        ->and($incomeTransaction->source_type)->toEqual(TransactionSourceType::Cuota)
        ->and($incomeTransaction->category_id)->toBeNull()
        ->and($incomeTransaction->reference_type)->toBe(Quota::class)
        ->and($incomeTransaction->reference_id)->toBe($quota->id);
});

test('markAsPaid asigna numero de socio cuando estaba pendiente sin numero', function () {
    Association::factory()->create([
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_receipt_number' => 0,
        'last_member_number' => 0,
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual sin numero',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 15,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => null,
        'first_name' => 'Silvia',
        'last_name' => 'Lozano',
        'status' => MemberStatus::Pendiente->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 15,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $quota->markAsPaid('Transferencia', 'Pago con alta automática');

    $member->refresh();

    expect($member->status)->toEqual(MemberStatus::Activo)
        ->and($member->member_number)->toBe('1')
        ->and($member->joined_at)->not()->toBeNull();
});

test('accion de editar cuota permite marcarla como pagada', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_receipt_number' => 0,
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3010',
        'first_name' => 'Raul',
        'last_name' => 'Mora',
        'status' => MemberStatus::Pendiente->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => '2026-05-01',
        'period_end' => '2026-05-31',
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    Livewire::test(EditQuota::class, ['record' => $quota->id])
        ->callAction('markAsPaid', [
            'payment_method' => 'Transferencia',
            'notes' => 'Pago desde accion de cabecera',
        ]);

    $quota->refresh();

    expect($quota->status)->toEqual(QuotaStatus::Pagada)
        ->and($quota->paid_at)->not()->toBeNull()
        ->and($quota->payment_method)->toBe('Transferencia');
});

test('la vista de cuota permite imprimir recibo en PDF para los cuatro tipos', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create();

    $quotaType = QuotaType::query()->create([
        'name' => 'Cuota mensual recibo',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3011',
        'first_name' => 'Celia',
        'last_name' => 'Ramos',
        'status' => MemberStatus::Activo->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => '2026-06-01',
        'period_end' => '2026-06-30',
        'amount' => 12,
        'status' => QuotaStatus::Pagada->value,
        'paid_at' => now(),
        'receipt_number' => '26-0001',
        'payment_method' => 'Transferencia',
    ]);

    foreach (['full', 'association', 'member', 'refund', 'refund_association', 'refund_member'] as $receiptType) {
        $response = $this->get(route('quotas.receipt.show', [
            'quota' => $quota->id,
            'type' => $receiptType,
        ]));

        $response->assertOk();

        expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
    }
});

test('listado de cuotas incluye exportacion inmediata con columnas opcionales', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(ListQuotas::class)
        ->assertTableHeaderActionsExistInOrder(['exportQuotas'])
        ->assertTableActionExists('exportQuotas')
        ->mountTableAction('exportQuotas')
        ->setTableActionData([
            'optional_columns' => [
                'due_date',
                'payment_method',
                'quota_type_name',
                'receipt_number',
            ],
        ])
        ->callMountedTableAction();
});

    test('listado de cuotas expone acciones de impresion por tipo', function () {
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        Livewire::test(ListQuotas::class)
        ->assertTableActionExists('printReceiptFull')
        ->assertTableActionExists('printReceiptAssociation')
        ->assertTableActionExists('printReceiptMember');
    });

test('al cancelar una cuota pagada se genera transacción de devolución', function () {
    Association::factory()->create([
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_receipt_number' => 0,
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3004',
        'first_name' => 'Sara',
        'last_name' => 'Gil',
        'status' => MemberStatus::Activo->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $quota->markAsPaid('Transferencia');
    $quota->update(['status' => QuotaStatus::Cancelada->value]);

    $member->refresh();

    $expenseTransaction = Transaction::query()
        ->where('type', TransactionType::Gasto->value)
        ->first();

    expect($expenseTransaction)->not()->toBeNull()
        ->and($expenseTransaction->source_type)->toEqual(TransactionSourceType::ReembolsoCuota)
        ->and($expenseTransaction->category_id)->toBeNull()
        ->and($member->status)->toEqual(MemberStatus::Pendiente);
});

test('no se pueden cancelar cuotas de periodos anteriores al actual', function () {
    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual pasado',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 30,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3006',
        'first_name' => 'Pastora',
        'last_name' => 'Lago',
        'status' => MemberStatus::Activo->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
        'period_end' => now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $quota->markAsPaid('Transferencia');

    expect(fn () => $quota->update(['status' => QuotaStatus::Cancelada->value]))
        ->toThrow(ValidationException::class);
});

test('se puede cancelar una cuota pagada dentro del plazo de devolución', function () {
    Association::factory()->create([
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_receipt_number' => 0,
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual con devolución',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'refund_days' => 5,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3007',
        'first_name' => 'Dora',
        'last_name' => 'Rey',
        'status' => MemberStatus::Activo->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $quota->markAsPaid('Transferencia');

    // Simulamos que pagó hace 3 días: dentro del plazo de 5 días
    $quota->forceFill(['paid_at' => now()->subDays(3)])->saveQuietly();

    expect(fn () => $quota->update(['status' => QuotaStatus::Cancelada->value]))
        ->not()->toThrow(ValidationException::class);

    $quota->refresh();
    expect($quota->status)->toEqual(QuotaStatus::Cancelada);
});

test('no se puede cancelar una cuota pagada fuera del plazo de devolución', function () {
    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual fuera de plazo',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'refund_days' => 2,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3009',
        'first_name' => 'Begoña',
        'last_name' => 'Reyes',
        'status' => MemberStatus::Activo->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $quota->markAsPaid('Transferencia');

    // Simulamos que pagó hace 3 días: fuera del plazo de 2 días
    $quota->forceFill(['paid_at' => now()->subDays(3)])->saveQuietly();

    expect(fn () => $quota->update(['status' => QuotaStatus::Cancelada->value]))
        ->toThrow(ValidationException::class);
});

test('cancelar una cuota futura pagada no afecta al estado actual del socio con cobertura vigente', function () {
    $currentQuotaType = QuotaType::query()->create([
        'name' => 'Tipo actual',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 10,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $futureQuotaType = QuotaType::query()->create([
        'name' => 'Tipo futuro',
        'period_type' => PaymentPeriod::Anual->value,
        'default_amount' => 60,
        'grace_days' => 10,
        'sort_order' => 2,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3008',
        'first_name' => 'Julia',
        'last_name' => 'Crespo',
        'status' => MemberStatus::Pendiente->value,
    ]);

    $currentQuota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $currentQuotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $currentQuota->markAsPaid('Transferencia');

    $futureQuota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $futureQuotaType->id,
        'period_start' => now()->addYear()->startOfYear()->toDateString(),
        'period_end' => now()->addYear()->endOfYear()->toDateString(),
        'amount' => 60,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $futureQuota->markAsPaid('Transferencia');
    $futureQuota->update(['status' => QuotaStatus::Cancelada->value]);

    $member->refresh();

    expect($member->status)->toEqual(MemberStatus::Activo);
});

test('una cuota pendiente vencida se informa como vencida', function () {
    $quota = Quota::query()->make([
        'status' => QuotaStatus::Pendiente->value,
        // Según la nueva regla, una cuota pendiente con due_date en el futuro
        // se considera "Vencida" para efectos informativos.
        'due_date' => now()->addDay()->toDateString(),
    ]);

    expect($quota->effectiveStatus())->toEqual(QuotaStatus::Vencida);
});

test('una cuota cancelada no bloquea crear otra para el mismo periodo', function () {
    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo trimestral cancelable',
        'period_type' => PaymentPeriod::Trimestral->value,
        'default_amount' => 20,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3005',
        'first_name' => 'Irene',
        'last_name' => 'Mena',
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => '2026-04-01',
        'period_end' => '2026-06-30',
        'amount' => 20,
        'status' => QuotaStatus::Cancelada->value,
    ]);

    expect(Quota::existsForMemberPeriod($member->id, '2026-04-01', '2026-06-30'))->toBeFalse()
        ->and(Quota::existsForMemberPeriod($member->id, '2026-04-01', '2026-06-30', $quota->id))->toBeFalse();
});

test('se puede crear una nueva cuota en el mismo periodo tras cancelar y pagar la anterior', function () {
    Association::factory()->create([
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_receipt_number' => 0,
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo anual repago',
        'period_type' => PaymentPeriod::Anual->value,
        'default_amount' => 60,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '5001',
        'first_name' => 'Carlos',
        'last_name' => 'Ibáñez',
        'status' => MemberStatus::Pendiente->value,
    ]);

    // 1. Crear y pagar la cuota original
    $original = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'amount' => 60,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $original->markAsPaid('Transferencia');

    // 2. Cancelarla (genera transacción de devolución y socio vuelve a Pendiente)
    $original->update(['status' => QuotaStatus::Cancelada->value]);
    $member->refresh();

    expect($member->status)->toEqual(MemberStatus::Pendiente);

    // 3. Crear nueva cuota para el mismo socio y mismo periodo — no debe lanzar excepción
    $nueva = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfYear()->toDateString(),
        'period_end' => now()->endOfYear()->toDateString(),
        'amount' => 60,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    expect($nueva->id)->not()->toBe($original->id)
        ->and($nueva->status)->toEqual(QuotaStatus::Pendiente)
        ->and(Quota::existsForMemberPeriod(
            $member->id,
            now()->startOfYear()->toDateString(),
            now()->endOfYear()->toDateString(),
        ))->toBeTrue();
});

test('existsForMemberPeriod detecta duplicados de periodo por socio', function () {
    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo trimestral',
        'period_type' => PaymentPeriod::Trimestral->value,
        'default_amount' => 20,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '3003',
        'first_name' => 'Nora',
        'last_name' => 'Diaz',
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => '2026-04-01',
        'period_end' => '2026-06-30',
        'amount' => 20,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    expect(Quota::existsForMemberPeriod($member->id, '2026-04-01', '2026-06-30'))->toBeTrue()
        ->and(Quota::existsForMemberPeriod($member->id, '2026-04-01', '2026-06-30', $quota->id))->toBeFalse();
});

test('no se puede eliminar una cuota desde el recurso', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '4001',
        'first_name' => 'Pedro',
        'last_name' => 'Soto',
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => '2026-01-01',
        'period_end' => '2026-01-31',
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    expect(\App\Filament\Resources\Quotas\QuotaResource::canDelete($quota))->toBeFalse()
        ->and(\App\Filament\Resources\Quotas\QuotaResource::canDeleteAny())->toBeFalse();
});

test('accion cancelar cuota desde tabla cambia estado y genera transaccion de reembolso si estaba pagada', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'receipt_number_mask' => '{Y:2}-{N:4}',
        'last_receipt_number' => 0,
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual cancelable',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 30,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '4002',
        'first_name' => 'Ana',
        'last_name' => 'Vega',
        'status' => MemberStatus::Activo->value,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $quota->markAsPaid('Transferencia');

    Livewire::test(ListQuotas::class)
        ->callTableAction('cancel', $quota)
        ->assertNotified();

    $quota->refresh();

    expect($quota->status)->toEqual(QuotaStatus::Cancelada);

    $refund = Transaction::query()
        ->where('type', TransactionType::Gasto->value)
        ->where('source_type', TransactionSourceType::ReembolsoCuota->value)
        ->first();

    expect($refund)->not()->toBeNull();
});

test('accion cancelar cuota desde edicion cambia estado', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create();

    $quotaType = QuotaType::query()->create([
        'name' => 'Tipo mensual edit',
        'period_type' => PaymentPeriod::Mensual->value,
        'default_amount' => 12,
        'grace_days' => 0,
        'sort_order' => 1,
        'align_period_start' => false,
    ]);

    $member = Member::query()->create([
        'member_number' => '4003',
        'first_name' => 'Rosa',
        'last_name' => 'Luna',
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'amount' => 12,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    Livewire::test(EditQuota::class, ['record' => $quota->id])
        ->callAction('cancel')
        ->assertNotified();

    $quota->refresh();

    expect($quota->status)->toEqual(QuotaStatus::Cancelada);
});
