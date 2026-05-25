<?php

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\PaymentMethod;
use App\Enums\QuotaStatus;
use App\Enums\TransactionType;
use App\Filament\Resources\Members\MemberResource;
use App\Filament\Resources\Members\Pages\CreateMember;
use App\Filament\Resources\Members\Pages\EditMember;
use App\Filament\Resources\Members\Pages\ViewNonMember;
use App\Filament\Resources\Members\RelationManagers\QuotasRelationManager;
use App\Filament\Resources\Members\RelationManagers\RegistrationsRelationManager;
use App\Models\Association;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('un no socio no ve cuotas pero si inscripciones como relaciones', function () {
    $nonMember = Member::query()->create([
        'first_name' => 'Nora',
        'last_name' => 'Vega',
        'member_type' => MemberType::NoSocio,
    ]);

    expect(QuotasRelationManager::canViewForRecord($nonMember, ViewNonMember::class))->toBeFalse()
        ->and(RegistrationsRelationManager::canViewForRecord($nonMember, ViewNonMember::class))->toBeTrue();
});

test('socios y no socios no se pueden eliminar desde el recurso', function () {
    $member = Member::query()->create([
        'member_number' => 'S-5001',
        'first_name' => 'Mario',
        'last_name' => 'Lago',
        'member_type' => MemberType::Socio,
    ]);

    $nonMember = Member::query()->create([
        'first_name' => 'Nora',
        'last_name' => 'Nadal',
        'member_type' => MemberType::NoSocio,
    ]);

    expect(MemberResource::canDelete($member))->toBeFalse()
        ->and(MemberResource::canDelete($nonMember))->toBeFalse()
        ->and(MemberResource::canDeleteAny())->toBeFalse();
});

test('se puede crear un socio desde el CRUD con defaults de negocio', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'payment_methods' => [PaymentMethod::Domiciliacion, PaymentMethod::Bizum],
    ]);

    Livewire::test(CreateMember::class)
        ->fillForm([
            'first_name' => 'Ana',
            'last_name' => 'Lopez',
            'payment_method_id' => PaymentMethod::Bizum->value,
        ])
        ->call('create')
        ->assertNotified()
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $member = Member::query()->first();

    expect($member)->not()->toBeNull()
        ->and($member->member_type)->toEqual(MemberType::Socio)
        ->and($member->status)->toEqual(MemberStatus::Pendiente)
        ->and($member->member_number)->toBeNull()
        ->and($member->gender)->toEqual(Gender::NoDefinido)
        ->and($member->payment_method_id)->toEqual(PaymentMethod::Bizum);
});

test('si no hay tipos de cuota, al activar el socio se guarda como pendiente', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'payment_methods' => [PaymentMethod::Transferencia],
        'payment_period' => 'Mensual',
    ]);

    Livewire::test(CreateMember::class)
        ->fillForm([
            'first_name' => 'Luis',
            'last_name' => 'Diaz',
            'payment_method_id' => PaymentMethod::Transferencia->value,
            'status' => MemberStatus::Activo->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $member = Member::query()->first();

    expect($member)->not()->toBeNull()
        ->and($member->status)->toEqual(MemberStatus::Pendiente)
        ->and($member->member_number)->toBeNull()
        ->and(Quota::query()->count())->toBe(0);
});

test('al activar con tipo de cuota seleccionado se crea la cuota para el socio', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'payment_methods' => [PaymentMethod::Transferencia],
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Cuota mensual base',
        'period_type' => 'Mensual',
        'period_reference_date' => now()->toDateString(),
        'default_amount' => 12.5,
        'grace_days' => 5,
        'align_period_start' => true,
    ]);

    Livewire::test(CreateMember::class)
        ->fillForm([
            'first_name' => 'Marta',
            'last_name' => 'Ruiz',
            'payment_method_id' => PaymentMethod::Transferencia->value,
            'status' => MemberStatus::Activo->value,
            'quota_id' => $quotaType->id,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $member = Member::query()->first();

    expect($member)->not()->toBeNull();

    $quota = Quota::query()->where('member_id', $member->id)->first();

    expect($quota)->not()->toBeNull()
        ->and($quota?->quota_type_id)->toBe($quotaType->id)
        ->and((float) $quota?->amount)->toBe(12.5)
        ->and($quota?->status)->toEqual(QuotaStatus::Pagada)
        ->and($quota?->paid_at)->not()->toBeNull()
        ->and($quota?->transactions()->where('type', TransactionType::Ingreso->value)->exists())->toBeTrue();

    $member->refresh();

    expect($member->status)->toEqual(MemberStatus::Activo)
        ->and($member->member_number)->toBe('1')
        ->and($member->joined_at)->not()->toBeNull();
});

test('si el socio activo tiene cuota actual pagada no puede pasar a pendiente, pero si a baja sin reembolso', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'payment_methods' => [PaymentMethod::Transferencia],
    ]);

    $member = Member::query()->create([
        'member_number' => 'S-0004',
        'first_name' => 'Pablo',
        'last_name' => 'Sanz',
        'status' => MemberStatus::Activo,
        'payment_method_id' => PaymentMethod::Transferencia,
    ]);

    $quotaType = QuotaType::query()->create([
        'name' => 'Cuota anual base',
        'period_type' => 'Anual',
        'period_reference_date' => now()->toDateString(),
        'default_amount' => 20,
        'grace_days' => 0,
        'align_period_start' => true,
    ]);

    $quota = Quota::query()->create([
        'member_id' => $member->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->startOfMonth()->toDateString(),
        'period_end' => now()->endOfMonth()->toDateString(),
        'due_date' => now()->toDateString(),
        'amount' => 20,
        'status' => QuotaStatus::Pagada,
        'paid_at' => now(),
        'payment_method' => PaymentMethod::Transferencia->value,
    ]);

    Livewire::test(EditMember::class, ['record' => $member->id])
        ->fillForm([
            'status' => MemberStatus::Pendiente->value,
        ])
        ->call('save')
        ->assertHasFormErrors(['status']);

    $member->refresh();

    expect($member->status)->toEqual(MemberStatus::Activo);

    $incomeCountBefore = $quota->transactions()
        ->where('type', TransactionType::Ingreso->value)
        ->count();

    Livewire::test(EditMember::class, ['record' => $member->id])
        ->fillForm([
            'status' => MemberStatus::Baja->value,
            'left_at' => now()->toDateString(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $member->refresh();
    $quota->refresh();

    expect($member->status)->toEqual(MemberStatus::Baja)
        ->and($quota->status)->toEqual(QuotaStatus::Pagada)
        ->and($quota->transactions()->where('type', TransactionType::Gasto->value)->exists())->toBeFalse()
        ->and($quota->transactions()->where('type', TransactionType::Ingreso->value)->count())->toBe($incomeCountBefore);
});
