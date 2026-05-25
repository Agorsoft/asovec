<?php

use App\Enums\EventType;
use App\Enums\EventStatus;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Enums\PaymentMethod;
use App\Enums\QuotaStatus;
use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Events\Pages\CreateEvent;
use App\Filament\Resources\Events\Pages\ViewEvent;
use App\Filament\Resources\Events\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\Registrations\Pages\CreateRegistration;
use App\Filament\Resources\Registrations\Pages\EditRegistration;
use App\Filament\Resources\Registrations\Pages\ListRegistrations;
use App\Filament\Resources\Registrations\Pages\ViewRegistration;
use App\Filament\Resources\Registrations\Schemas\RegistrationForm;
use App\Models\Association;
use App\Models\Event;
use App\Models\Member;
use App\Models\Quota;
use App\Models\QuotaType;
use App\Models\Registration;
use App\Models\Transaction;
use App\Models\User;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('el recurso de eventos registra la relacion de inscripciones', function () {
    expect(EventResource::getRelations())
        ->toContain(RegistrationsRelationManager::class);
});

test('evento solo se puede eliminar si no tiene inscripciones', function () {
    $eventWithoutRegistrations = Event::factory()->create();

    $eventWithRegistrations = Event::factory()->create();

    $participant = Member::query()->create([
        'member_number' => 'NM-9000',
        'first_name' => 'Teo',
        'last_name' => 'Registro',
        'member_type' => MemberType::NoSocio->value,
    ]);

    Registration::query()->create([
        'event_id' => $eventWithRegistrations->id,
        'member_id' => $participant->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Pendiente->value,
    ]);

    expect(EventResource::canDelete($eventWithoutRegistrations))->toBeTrue()
        ->and(EventResource::canDelete($eventWithRegistrations))->toBeFalse();
});

test('se puede crear un evento desde el CRUD', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreateEvent::class)
        ->fillForm([
            'name' => 'Excursión Sierra Norte',
            'event_type' => 'Evento',
            'status' => 'Abierto',
            'start_date' => now()->addWeek()->format('Y-m-d H:i:s'),
            'end_date' => now()->addWeek()->addHours(5)->format('Y-m-d H:i:s'),
            'registration_deadline' => now()->addDays(5)->toDateString(),
            'max_companions_per_member' => 2,
            'base_price' => 20,
            'companion_surcharge' => 5,
            'non_member_surcharge' => 10,
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $event = Event::query()->first();

    expect($event)->not()->toBeNull()
        ->and($event->event_code)->toMatch('/^EVT-\d{2}-\d{4}$/')
        ->and($event->name)->toBe('Excursión Sierra Norte');
});

test('los eventos de venta usan el prefijo VNT en el codigo', function () {
    $event = Event::factory()->create([
        'event_type' => EventType::Venta->value,
    ]);

    expect($event->event_code)->toMatch('/^VNT-\d{2}-\d{4}$/');
});

test('se puede crear una inscripcion desde el CRUD', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'payment_methods' => [PaymentMethod::Transferencia],
    ]);

    $event = Event::factory()->create([
        'event_code' => 'ALT-2026',
        'base_price' => 12,
        'non_member_surcharge' => 3,
    ]);

    $nonMember = Member::query()->create([
        'member_number' => 'NM-9050',
        'first_name' => 'Leo',
        'last_name' => 'Toro',
        'member_type' => MemberType::NoSocio->value,
    ]);

    Livewire::test(CreateRegistration::class)
        ->fillForm([
            'event_id' => $event->id,
            'member_id' => $nonMember->id,
            'registration_type' => RegistrationType::NoSocio->value,
            'status' => RegistrationStatus::Pendiente->value,
            'payment_method' => 'Transferencia',
            'amount' => 12,
            'surcharge_amount' => 3,
            'notes' => 'Inscripción de prueba',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $registration = Registration::query()->first();

    expect($registration)->not()->toBeNull()
        ->and($registration->registration_number)->toBe('ALT-2026-0001')
        ->and($registration->registration_type)->toEqual(RegistrationType::NoSocio);
});

test('el importe total de la inscripcion se calcula y se muestra en el recibo', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $association = Association::factory()->create();

    $event = Event::factory()->create([
        'event_code' => 'TOT-2026',
        'base_price' => 12,
        'non_member_surcharge' => 3,
    ]);

    $nonMember = Member::query()->create([
        'member_number' => 'NM-9051',
        'first_name' => 'Sara',
        'last_name' => 'Luna',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $registration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $nonMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    expect($registration->total_amount)->toBe('15.00');

    $html = view('documents.registrations.RegistrationReceiptA4', [
        'registration' => $registration->loadMissing(['event', 'member']),
        'association' => $association,
    ])->render();

    expect($html)->toContain('TOTAL: 15,00 EUR');
});

test('crear un no socio no consume numero de socio ni incrementa la asociacion', function () {
    $association = Association::factory()->create([
        'last_member_number' => 7,
    ]);

    $method = new ReflectionMethod(RegistrationForm::class, 'createNonMember');
    $method->setAccessible(true);

    $memberId = $method->invoke(null, [
        'first_name' => 'Nuevo',
        'last_name' => 'NoSocio',
        'gender' => 'No Definido',
    ]);

    $member = Member::query()->find($memberId);

    expect($member)->not()->toBeNull()
        ->and($member?->member_number)->toBeNull()
        ->and($association->refresh()->last_member_number)->toBe(7);
});

test('el tipo de inscripcion acompañante solo aparece cuando el evento lo permite', function () {
    $eventSinAcompanantes = Event::factory()->create([
        'max_companions_per_member' => 0,
    ]);

    $eventConAcompanantes = Event::factory()->create([
        'max_companions_per_member' => 2,
    ]);

    expect(RegistrationForm::registrationTypeOptions($eventSinAcompanantes))
        ->not()->toHaveKey(RegistrationType::Acompanante->value)
        ->and(RegistrationForm::registrationTypeOptions($eventConAcompanantes))
        ->toHaveKey(RegistrationType::Acompanante->value);
});

test('los metodos de pago se leen desde la asociacion y se preseleccionan si solo hay uno', function () {
    Association::factory()->create([
        'payment_methods' => [PaymentMethod::Bizum],
    ]);

    expect(RegistrationForm::paymentMethodOptions())
        ->toMatchArray([
            PaymentMethod::Bizum->value => PaymentMethod::Bizum->label(),
        ])
        ->and(RegistrationForm::defaultPaymentMethod())
        ->toBe(PaymentMethod::Bizum->value);
});

test('el boton de alta rapida solo aparece para los tipos permitidos', function () {
    expect(RegistrationForm::canCreateMemberOption(RegistrationType::NoSocio))
        ->toBeTrue()
        ->and(RegistrationForm::canCreateMemberOption(RegistrationType::Socio))
        ->toBeFalse()
        ->and(RegistrationForm::canCreateMemberOption(RegistrationType::Acompanante))
        ->toBeFalse()
        ->and(RegistrationForm::canCreateSponsorOption(RegistrationType::Acompanante))
        ->toBeTrue()
        ->and(RegistrationForm::canCreateSponsorOption(RegistrationType::NoSocio))
        ->toBeFalse();
});

test('las opciones de acompañante incluyen no socios y socios no activos', function () {
    Member::query()->create([
        'member_number' => 'NM-9901',
        'first_name' => 'No',
        'last_name' => 'Socio',
        'member_type' => MemberType::NoSocio->value,
        'status' => MemberStatus::Pendiente->value,
    ]);

    Member::query()->create([
        'member_number' => 'SO-9901',
        'first_name' => 'Socio',
        'last_name' => 'Baja',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Baja->value,
    ]);

    Member::query()->create([
        'member_number' => 'SO-9902',
        'first_name' => 'Socio',
        'last_name' => 'Pendiente',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Pendiente->value,
    ]);

    Member::query()->create([
        'member_number' => 'SO-9903',
        'first_name' => 'Socio',
        'last_name' => 'Activo',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Activo->value,
    ]);

    $get = \Mockery::mock(Get::class);
    $get->shouldReceive('__invoke')
        ->with('registration_type')
        ->andReturn(RegistrationType::Acompanante->value);

    $method = new ReflectionMethod(RegistrationForm::class, 'sponsorOptions');
    $method->setAccessible(true);

    /** @var array<string, string> $options */
    $options = $method->invoke(null, $get);

    $values = array_values($options);

    expect($values)
        ->toContain('No Socio')
        ->toContain('Socio Baja')
        ->toContain('Socio Pendiente')
        ->not->toContain('Socio Activo');
});

test('inscripcion no socio con socio pendiente se bloquea con mensaje especifico', function () {
    $event = Event::factory()->create();

    $pendingMember = Member::query()->create([
        'member_number' => 'SO-9910',
        'first_name' => 'Socio',
        'last_name' => 'Pendiente',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Pendiente->value,
    ]);

    expect(fn () => Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $pendingMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]))
        ->toThrow(ValidationException::class, 'El participante consta como socio pendiente. Deberá darse de baja o continuar con el pago de la cuota.');
});

test('numeracion de inscripciones se reinicia por evento', function () {
    $eventA = Event::factory()->create(['event_code' => 'EVA-2026']);
    $eventB = Event::factory()->create(['event_code' => 'EVB-2026']);

    $nonMemberOne = Member::query()->create([
        'member_number' => 'NM-9001',
        'first_name' => 'Lola',
        'last_name' => 'Paz',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $nonMemberTwo = Member::query()->create([
        'member_number' => 'NM-9002',
        'first_name' => 'Mara',
        'last_name' => 'Cruz',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $nonMemberThree = Member::query()->create([
        'member_number' => 'NM-9003',
        'first_name' => 'Nora',
        'last_name' => 'Silva',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $registrationA1 = Registration::query()->create([
        'event_id' => $eventA->id,
        'member_id' => $nonMemberOne->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    $registrationA2 = Registration::query()->create([
        'event_id' => $eventA->id,
        'member_id' => $nonMemberTwo->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    $registrationB1 = Registration::query()->create([
        'event_id' => $eventB->id,
        'member_id' => $nonMemberThree->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    expect($registrationA1->registration_number)->toBe('EVA-2026-0001')
        ->and($registrationA2->registration_number)->toBe('EVA-2026-0002')
        ->and($registrationB1->registration_number)->toBe('EVB-2026-0001');
});

test('acompanante requiere socio patrocinador', function () {
    $event = Event::factory()->create([
        'max_companions_per_member' => 1,
    ]);

    $companion = Member::query()->create([
        'member_number' => 'NM-9010',
        'first_name' => 'Ana',
        'last_name' => 'Rios',
        'member_type' => MemberType::NoSocio->value,
    ]);

    expect(fn () => Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $companion->id,
        'registration_type' => RegistrationType::Acompanante->value,
    ]))->toThrow(ValidationException::class);
});

test('acompanante permite participante socio pendiente con patrocinador activo', function () {
    $event = Event::factory()->create([
        'max_companions_per_member' => 2,
    ]);

    $pendingCompanion = Member::query()->create([
        'member_number' => 'SO-9911',
        'first_name' => 'Compa',
        'last_name' => 'Pendiente',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Pendiente->value,
    ]);

    $activeSponsor = Member::query()->create([
        'member_number' => 'SO-9912',
        'first_name' => 'Sponsor',
        'last_name' => 'Activo',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Activo->value,
    ]);

    $registration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $pendingCompanion->id,
        'sponsor_member_id' => $activeSponsor->id,
        'registration_type' => RegistrationType::Acompanante->value,
    ]);

    expect($registration)
        ->not()->toBeNull()
        ->and($registration->registration_type)
        ->toEqual(RegistrationType::Acompanante);
});

test('confirmar inscripcion genera transacciones de ingreso', function () {
    $event = Event::factory()->create([
        'event_code' => 'ING-2026',
        'base_price' => 20,
        'non_member_surcharge' => 5,
    ]);

    $nonMember = Member::query()->create([
        'member_number' => 'NM-9020',
        'first_name' => 'Iria',
        'last_name' => 'Mora',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $registration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $nonMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    $registration->confirm('Transferencia', 'Pago de prueba');
    $registration->refresh();

    $rootIncome = $registration->transactions()
        ->where('type', TransactionType::Ingreso->value)
        ->whereNull('parent_transaction_id')
        ->first();

    expect($registration->status)->toEqual(RegistrationStatus::Confirmada)
        ->and($registration->paid_at)->not()->toBeNull()
        ->and($rootIncome)->not()->toBeNull();
});

test('crear inscripcion ya confirmada genera transacciones y desglosa recargo', function () {
    $event = Event::factory()->create([
        'event_code' => 'ACC-2026',
        'event_type' => EventType::Evento->value,
        'name' => 'Salida contable',
        'base_price' => 20,
        'non_member_surcharge' => 5,
    ]);

    $nonMember = Member::query()->create([
        'member_number' => 'NM-9021',
        'first_name' => 'Alba',
        'last_name' => 'Cano',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $registration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $nonMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Confirmada->value,
        'payment_method' => PaymentMethod::Transferencia->value,
    ]);

    $registration->refresh();

    $rootIncome = $registration->transactions()
        ->where('type', TransactionType::Ingreso->value)
        ->whereNull('parent_transaction_id')
        ->first();

    $children = $registration->transactions()
        ->where('type', TransactionType::Ingreso->value)
        ->whereNotNull('parent_transaction_id')
        ->get();

    expect($registration->paid_at)->not()->toBeNull()
        ->and($rootIncome)->not()->toBeNull()
        ->and($rootIncome?->amount)->toBe('25.00')
        ->and($rootIncome?->source_type)->toEqual(TransactionSourceType::InscripcionEvento)
        ->and($rootIncome?->category_id)->toBeNull()
        ->and($rootIncome?->receipt_number)->toBe($registration->registration_number)
        ->and($rootIncome?->description)->toContain($registration->registration_number)
        ->and($children)->toHaveCount(2)
        ->and($children->where('amount', '20.00')->where('source_type', TransactionSourceType::BaseInscripcionEvento)->isNotEmpty())->toBeTrue()
        ->and($children->where('amount', '5.00')->where('source_type', TransactionSourceType::RecargoEvento)->isNotEmpty())->toBeTrue()
        ->and($children->every(fn ($transaction): bool => $transaction->receipt_number === $registration->registration_number))->toBeTrue();
});

test('transaction distingue base y recargo para filtrar y totalizar', function () {
    $event = Event::factory()->create([
        'event_code' => 'EVT-2026',
        'event_type' => EventType::Evento->value,
        'base_price' => 20,
        'non_member_surcharge' => 5,
    ]);

    $specialSale = Event::factory()->create([
        'event_code' => 'VNT-2026',
        'event_type' => EventType::Venta->value,
        'base_price' => 30,
        'non_member_surcharge' => 7,
    ]);

    $firstNonMember = Member::query()->create([
        'member_number' => 'NM-9201',
        'first_name' => 'Base',
        'last_name' => 'Evento',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $secondNonMember = Member::query()->create([
        'member_number' => 'NM-9202',
        'first_name' => 'Base',
        'last_name' => 'Especial',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $registrationEvent = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $firstNonMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    $registrationSpecial = Registration::query()->create([
        'event_id' => $specialSale->id,
        'member_id' => $secondNonMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    $registrationEvent->confirm('Transferencia');
    $registrationSpecial->confirm('Transferencia');

    $baseTotal = (float) Transaction::query()
        ->where('type', TransactionType::Ingreso->value)
        ->basePrice()
        ->sum('amount');

    $surchargeTotal = (float) Transaction::query()
        ->where('type', TransactionType::Ingreso->value)
        ->surcharge()
        ->sum('amount');

    expect($baseTotal)->toBe(50.0)
        ->and($surchargeTotal)->toBe(12.0)
        ->and(Transaction::query()->basePrice()->count())->toBeGreaterThanOrEqual(2)
        ->and(Transaction::query()->surcharge()->count())->toBeGreaterThanOrEqual(2);
});

test('cancelar una inscripcion confirmada genera transaccion de reembolso', function () {
    $event = Event::factory()->create(['event_type' => EventType::Evento->value]);

    $nonMember = Member::query()->create([
        'member_number' => 'NM-9030',
        'first_name' => 'Rosa',
        'last_name' => 'Llanos',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $registration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $nonMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    $registration->confirm('Transferencia');
    $registration->update(['status' => RegistrationStatus::Cancelada->value]);

    $expense = $registration->transactions()
        ->where('type', TransactionType::Gasto->value)
        ->whereNull('parent_transaction_id')
        ->first();

    expect($expense)->not()->toBeNull()
        ->and($expense->source_type)->toEqual(TransactionSourceType::ReembolsoInscripcionEvento)
        ->and($expense->category_id)->toBeNull();
});

test('cancelar evento cancela inscripciones pendientes y confirmadas', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Abierto->value,
    ]);

    $pendingMember = Member::query()->create([
        'member_number' => 'NM-9301',
        'first_name' => 'Pau',
        'last_name' => 'Pendiente',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $confirmedMember = Member::query()->create([
        'member_number' => 'NM-9302',
        'first_name' => 'Pau',
        'last_name' => 'Confirmado',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $pendingRegistration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $pendingMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Pendiente->value,
    ]);

    $confirmedRegistration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $confirmedMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Confirmada->value,
        'payment_method' => PaymentMethod::Transferencia->value,
    ]);

    $event->update([
        'status' => EventStatus::Cancelado->value,
    ]);

    expect($pendingRegistration->refresh()->status)->toEqual(RegistrationStatus::Cancelada)
        ->and($confirmedRegistration->refresh()->status)->toEqual(RegistrationStatus::Cancelada);
});

test('cancelar evento reembolsa solo inscripciones confirmadas', function () {
    $event = Event::factory()->create([
        'status' => EventStatus::Abierto->value,
        'base_price' => 25,
        'non_member_surcharge' => 5,
    ]);

    $pendingMember = Member::query()->create([
        'member_number' => 'NM-9303',
        'first_name' => 'Roi',
        'last_name' => 'Pendiente',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $confirmedMember = Member::query()->create([
        'member_number' => 'NM-9304',
        'first_name' => 'Roi',
        'last_name' => 'Confirmado',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $pendingRegistration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $pendingMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Pendiente->value,
    ]);

    $confirmedRegistration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $confirmedMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Confirmada->value,
        'payment_method' => PaymentMethod::Transferencia->value,
    ]);

    $event->update([
        'status' => EventStatus::Cancelado->value,
    ]);

    $pendingExpenses = $pendingRegistration->transactions()
        ->where('type', TransactionType::Gasto->value)
        ->count();

    $confirmedExpenses = $confirmedRegistration->transactions()
        ->where('type', TransactionType::Gasto->value)
        ->count();

    expect($pendingExpenses)->toBe(0)
        ->and($confirmedExpenses)->toBeGreaterThan(0);
});

test('accion de editar inscripcion permite confirmar', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'payment_methods' => [PaymentMethod::Transferencia],
    ]);

    $event = Event::factory()->create();

    $nonMember = Member::query()->create([
        'member_number' => 'NM-9040',
        'first_name' => 'Lia',
        'last_name' => 'Mendez',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $registration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $nonMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    Livewire::test(EditRegistration::class, ['record' => $registration->id])
        ->callAction('confirm', [
            'payment_method' => 'Transferencia',
            'notes' => 'Confirmada desde acción de cabecera',
        ]);

    $registration->refresh();

    expect($registration->status)->toEqual(RegistrationStatus::Confirmada)
        ->and($registration->paid_at)->not()->toBeNull();
});

test('la impresion de recibo permite completo, asociacion, socio y devolucion', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create();

    $event = Event::factory()->create();

    $participant = Member::query()->create([
        'member_number' => 'NM-9998',
        'first_name' => 'Dani',
        'last_name' => 'Prado',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $registration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $participant->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    foreach (['full', 'association', 'member', 'refund', 'refund_association', 'refund_member'] as $receiptType) {
        $response = $this->get(route('registrations.receipt.show', [
            'registration' => $registration->id,
            'type' => $receiptType,
        ]));

        $response->assertOk();

        expect((string) $response->headers->get('content-type'))->toContain('application/pdf');
    }
});

test('inscripcion duplicada muestra notificacion de error', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $event = Event::factory()->create();

    $member = Member::query()->create([
        'member_number' => null,
        'first_name' => 'Pedro',
        'last_name' => 'Gil',
        'member_type' => MemberType::NoSocio->value,
    ]);

    // Crear la primera inscripción directamente en el modelo.
    Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $member->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    // Intentar crear una segunda inscripción para el mismo participante.
    Livewire::test(CreateRegistration::class)
        ->fillForm([
            'event_id' => $event->id,
            'member_id' => $member->id,
            'registration_type' => RegistrationType::NoSocio->value,
        ])
        ->call('create')
        ->assertNotified('No se puede guardar la inscripción');

    // No debe haberse creado una segunda inscripción.
    expect(Registration::query()->count())->toBe(1);
});

test('vista de evento permite exportar inscripciones con cabecera de evento', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $event = Event::factory()->create([
        'event_code' => 'EVT-2601',
        'name' => 'Salida de prueba',
    ]);

    $participant = Member::query()->create([
        'member_number' => 'NM-9500',
        'first_name' => 'Paz',
        'last_name' => 'Noriega',
        'member_type' => MemberType::NoSocio->value,
    ]);

    Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $participant->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    Livewire::test(ViewEvent::class, ['record' => $event->id])
        ->callAction('exportEventRegistrations');
});

test('un socio con status Pendiente no puede inscribirse como Socio', function () {
    Association::factory()->create(['payment_methods' => [PaymentMethod::Transferencia]]);

    $event = Event::factory()->create(['base_price' => 10]);

    $pendingMember = Member::query()->create([
        'member_number' => 'S-0099',
        'first_name' => 'Clara',
        'last_name' => 'Vidal',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Pendiente->value,
    ]);

    expect(fn () => Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $pendingMember->id,
        'registration_type' => RegistrationType::Socio->value,
    ]))->toThrow(Illuminate\Validation\ValidationException::class, 'al corriente de pago');
});

test('un socio con status Baja puede inscribirse como NoSocio', function () {
    Association::factory()->create(['payment_methods' => [PaymentMethod::Transferencia]]);

    $event = Event::factory()->create(['base_price' => 10, 'non_member_surcharge' => 5]);

    $bajaMember = Member::query()->create([
        'member_number' => 'S-0100',
        'first_name' => 'Juan',
        'last_name' => 'Ríos',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Baja->value,
    ]);

    $registration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $bajaMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
    ]);

    expect($registration->registration_type)->toEqual(RegistrationType::NoSocio);
});

test('el comando members:apply-overdue-baja pasa a Baja socios Pendientes con cuota vencida', function () {
    $quotaType = QuotaType::query()->create([
        'name' => 'Cuota anual',
        'period_type' => 'Anual',
        'period_reference_date' => now()->toDateString(),
        'default_amount' => 20,
        'grace_days' => 0,
        'align_period_start' => false,
    ]);

    $pendingMember = Member::query()->create([
        'member_number' => 'S-0200',
        'first_name' => 'Bea',
        'last_name' => 'Soto',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Pendiente->value,
    ]);

    Quota::query()->create([
        'member_id' => $pendingMember->id,
        'quota_type_id' => $quotaType->id,
        'period_start' => now()->subYear()->toDateString(),
        'period_end' => now()->subMonths(6)->toDateString(),
        'due_date' => now()->subDays(10)->toDateString(),
        'amount' => 20,
        'status' => QuotaStatus::Pendiente->value,
    ]);

    $this->artisan('members:apply-overdue-baja')->assertSuccessful();

    expect($pendingMember->refresh()->status)->toEqual(MemberStatus::Baja);
});

test('el comando members:apply-overdue-baja no afecta a socios Pendientes sin cuota vencida', function () {
    $pendingMember = Member::query()->create([
        'member_number' => 'S-0201',
        'first_name' => 'Teo',
        'last_name' => 'Blanco',
        'member_type' => MemberType::Socio->value,
        'status' => MemberStatus::Pendiente->value,
    ]);

    $this->artisan('members:apply-overdue-baja')->assertSuccessful();

    expect($pendingMember->refresh()->status)->toEqual(MemberStatus::Pendiente);
});

test('el listado de inscripciones solo muestra pendientes y confirmadas', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $event = Event::factory()->create();

    $pendingMember = Member::query()->create([
        'member_number' => 'NM-9801',
        'first_name' => 'Nora',
        'last_name' => 'Pendiente',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $confirmedMember = Member::query()->create([
        'member_number' => 'NM-9802',
        'first_name' => 'Nora',
        'last_name' => 'Confirmada',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $cancelledMember = Member::query()->create([
        'member_number' => 'NM-9803',
        'first_name' => 'Nora',
        'last_name' => 'Cancelada',
        'member_type' => MemberType::NoSocio->value,
    ]);

    $pendingRegistration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $pendingMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Pendiente->value,
    ]);

    $confirmedRegistration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $confirmedMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Confirmada->value,
        'payment_method' => PaymentMethod::Transferencia->value,
    ]);

    $cancelledRegistration = Registration::query()->create([
        'event_id' => $event->id,
        'member_id' => $cancelledMember->id,
        'registration_type' => RegistrationType::NoSocio->value,
        'status' => RegistrationStatus::Pendiente->value,
    ]);

    $cancelledRegistration->update([
        'status' => RegistrationStatus::Cancelada->value,
    ]);

    Livewire::test(ListRegistrations::class)
        ->assertCanSeeTableRecords([$pendingRegistration, $confirmedRegistration])
        ->assertCanNotSeeTableRecords([$cancelledRegistration]);
});
