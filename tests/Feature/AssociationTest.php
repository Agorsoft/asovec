<?php

use App\Enums\PaymentMethod;
use App\Enums\PaymentPeriod;
use App\Models\Association;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('se puede crear una asociación con datos básicos', function () {
    $association = Association::factory()->create([
        'name' => 'Asociación de Prueba',
        'country' => 'ES',
    ]);

    expect($association)->toBeInstanceOf(Association::class)
        ->and($association->name)->toBe('Asociación de Prueba')
        ->and($association->country)->toBe('ES');
});

test('payment_period se castea a enum PaymentPeriod', function () {
    $association = Association::factory()->create([
        'payment_period' => PaymentPeriod::Anual,
    ]);

    expect($association->fresh()->payment_period)->toEqual(PaymentPeriod::Anual);
});

test('payment_methods se castea a colección de enums PaymentMethod', function () {
    $methods = [PaymentMethod::Efectivo, PaymentMethod::Transferencia];

    $association = Association::factory()->create([
        'payment_methods' => $methods,
    ]);

    $stored = $association->fresh()->payment_methods;

    expect($stored)->toHaveCount(2)
        ->and($stored->first())->toEqual(PaymentMethod::Efectivo);
});

test('last_member_number y last_receipt_number arrancan en cero', function () {
    $association = Association::factory()->create();

    expect($association->last_member_number)->toBe(0)
        ->and($association->last_receipt_number)->toBe(0);
});
