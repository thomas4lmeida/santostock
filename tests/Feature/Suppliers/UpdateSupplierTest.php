<?php

use App\Enums\Role;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can update a supplier', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $supplier = Supplier::factory()->create(['name' => 'Antigo']);

    $response = $this->actingAs($admin)->put("/suppliers/{$supplier->id}", [
        'name' => 'Novo',
        'contact_name' => 'Maria',
        'phone' => null,
        'email' => null,
        'notes' => null,
    ]);

    $response->assertRedirect('/suppliers');
    expect($supplier->fresh()->name)->toBe('Novo');
});

test('admin can delete a supplier', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $supplier = Supplier::factory()->create();

    $this->actingAs($admin)->delete("/suppliers/{$supplier->id}")
        ->assertRedirect('/suppliers');

    $this->assertDatabaseMissing('suppliers', ['id' => $supplier->id]);
});

test('name is required on create', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->post('/suppliers', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('email must be valid', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->post('/suppliers', [
        'name' => 'Teste',
        'email' => 'not-an-email',
    ])->assertSessionHasErrors('email');
});
