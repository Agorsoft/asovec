<?php

use App\Models\Association;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('la pagina de mandato sepa requiere autenticacion', function () {
    $association = Association::factory()->create();

    $response = $this->get(route('associations.sepa.print', $association));

    $response->assertRedirect(route('login'));
});

test('la pagina de mandato sepa muestra los datos de la asociacion', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $association = Association::factory()->create([
        'name' => 'Asociacion Vecinal Centro',
        'tax_id' => 'G12345678',
        'creditor_identifier' => 'ES99ZZZ123456789',
        'address' => 'Calle Mayor 1',
        'postal_code' => '28001',
        'city' => 'Madrid',
        'province' => 'Madrid',
        'country' => 'ES',
    ]);

    $response = $this->actingAs($user)
        ->get(route('associations.sepa.print', $association));

    $response->assertOk()
        ->assertSee('MANDATO DE DOMICILIACION')
        ->assertSee('Asociacion Vecinal Centro')
        ->assertSee('G12345678')
        ->assertSee('ES99ZZZ123456789');
});

test('el indice de asociaciones redirige al setup cuando no existe asociacion', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    expect(Association::query()->count())->toBe(0);

    $response = $this->actingAs($user)->get('/admin/associations');

    $response->assertRedirect('/admin/associations/setup');

    expect(Association::query()->count())->toBe(0);
});

test('el inicio del panel redirige al setup cuando no existe asociacion', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $response = $this->actingAs($user)->get('/admin');

    $response->assertRedirect('/admin/associations/setup');
});

test('el indice de asociaciones redirige a la vista cuando ya existe asociacion', function () {
    $user = User::factory()->create([
        'is_admin' => true,
    ]);

    $association = Association::factory()->create();

    $response = $this->actingAs($user)->get('/admin/associations');

    $response->assertRedirect('/admin/associations/'.$association->id);
});
