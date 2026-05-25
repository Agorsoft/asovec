<?php

use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use App\Filament\Resources\Transactions\Pages\CreateTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Association;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('se puede crear una transaccion desde el CRUD', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create([
        'payment_methods' => ['Transferencia'],
    ]);

    Livewire::test(CreateTransaction::class)
        ->fillForm([
            'type' => TransactionType::Ingreso->value,
            'amount' => 30,
            'date' => '2026-01-15',
            'payment_method' => 'Transferencia',
            'description' => 'Cobro manual de cuota',
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'Creada desde test',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $transaction = Transaction::query()->first();

    expect($transaction)->not()->toBeNull()
        ->and($transaction->type)->toEqual(TransactionType::Ingreso)
        ->and((float) $transaction->amount)->toBe(30.0)
        ->and($transaction->source_type)->toEqual(TransactionSourceType::Manual)
        ->and($transaction->category_id)->toBeNull()
        ->and($transaction->description)->toBe('Cobro manual de cuota');
});

test('listado de transacciones incluye exportacion inmediata con columnas opcionales', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Transaction::factory()->create([
        'type' => TransactionType::Ingreso->value,
        'amount' => 100,
        'date' => now()->subDay(),
        'description' => 'Test transaction',
        'payment_method' => 'Transferencia',
        'receipt_number' => 'REC001',
        'notes' => 'Test notes',
    ]);

    Livewire::test(ListTransactions::class)
        ->assertTableHeaderActionsExistInOrder(['exportTransactions'])
        ->assertTableActionExists('exportTransactions')
        ->mountTableAction('exportTransactions')
        ->setTableActionData([
            'optional_columns' => [
                'payment_method',
                'receipt_number',
                'notes',
            ],
        ])
        ->callMountedTableAction();
});

test('solo se puede editar transacciones manuales desde el listado', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $manualTransaction = Transaction::factory()->create([
        'source_type' => TransactionSourceType::Manual,
    ]);

    $nonManualTransaction = Transaction::factory()->create([
        'source_type' => TransactionSourceType::Cuota,
    ]);

    expect(TransactionResource::canEdit($manualTransaction))->toBeTrue()
        ->and(TransactionResource::canEdit($nonManualTransaction))->toBeFalse()
        ->and(TransactionResource::canDelete($manualTransaction))->toBeTrue()
        ->and(TransactionResource::canDelete($nonManualTransaction))->toBeFalse();
});

test('editar transaccion no manual redirige a vista en lugar de devolver 403', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Association::factory()->create();

    $nonManualTransaction = Transaction::factory()->create([
        'source_type' => TransactionSourceType::Cuota,
    ]);

    $this->get(TransactionResource::getUrl('edit', ['record' => $nonManualTransaction]))
        ->assertRedirect(TransactionResource::getUrl('view', ['record' => $nonManualTransaction]));
});
