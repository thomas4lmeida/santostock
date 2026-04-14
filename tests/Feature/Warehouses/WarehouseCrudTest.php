<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can list warehouses', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->get('/armazens')->assertOk();
});

test('admin can view create form', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->get('/armazens/create')->assertOk();
});

test('admin can create a warehouse', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $response = $this->actingAs($admin)->post('/armazens', [
        'name' => 'Armazém Central',
    ]);

    $response->assertRedirect('/armazens');
    $this->assertDatabaseHas('warehouses', ['name' => 'Armazém Central']);
});

test('admin can view a warehouse', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($admin)->get("/armazens/{$warehouse->id}")->assertOk();
});

test('admin can view edit form', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($admin)->get("/armazens/{$warehouse->id}/edit")->assertOk();
});

test('admin can update a warehouse', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $warehouse = Warehouse::factory()->create(['name' => 'Depósito Antigo']);

    $response = $this->actingAs($admin)->put("/armazens/{$warehouse->id}", [
        'name' => 'Depósito Novo',
    ]);

    $response->assertRedirect('/armazens');
    $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Depósito Novo']);
});

test('admin can delete a warehouse', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($admin)->delete("/armazens/{$warehouse->id}")->assertRedirect('/armazens');

    $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
});

test('name is required', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->post('/armazens', [])->assertSessionHasErrors('name');
});

test('name must be unique', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    Warehouse::factory()->create(['name' => 'Armazém Duplicado']);

    $this->actingAs($admin)->post('/armazens', [
        'name' => 'Armazém Duplicado',
    ])->assertSessionHasErrors('name');
});

test('name unique rule ignores current warehouse on update', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $warehouse = Warehouse::factory()->create(['name' => 'Armazém Leste']);

    $this->actingAs($admin)->put("/armazens/{$warehouse->id}", [
        'name' => 'Armazém Leste',
    ])->assertRedirect('/armazens');

    $this->assertDatabaseHas('warehouses', ['id' => $warehouse->id, 'name' => 'Armazém Leste']);
});
