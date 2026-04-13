<?php

use App\Enums\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('coordinator can update a supplier', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $supplier = Supplier::factory()->create(['name' => 'Antigo']);

    $response = $this->actingAs($coordinator)->put("/suppliers/{$supplier->id}", [
        'name' => 'Novo',
        'contact_name' => 'Maria',
        'phone' => null,
        'email' => null,
        'notes' => null,
    ]);

    $response->assertRedirect('/suppliers');
    expect($supplier->fresh()->name)->toBe('Novo');
});

test('coordinator can delete a supplier', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $supplier = Supplier::factory()->create();

    $this->actingAs($coordinator)->delete("/suppliers/{$supplier->id}")
        ->assertRedirect('/suppliers');

    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});

test('name is required on create', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);

    $this->actingAs($coordinator)->post('/suppliers', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('email must be valid', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);

    $this->actingAs($coordinator)->post('/suppliers', [
        'name' => 'Teste',
        'email' => 'not-an-email',
    ])->assertSessionHasErrors('email');
});
