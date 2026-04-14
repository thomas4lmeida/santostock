<?php

use App\Enums\Role;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('operador can list warehouses', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->get('/armazens')->assertOk();
});

test('operador can view a warehouse', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($operador)->get("/armazens/{$warehouse->id}")->assertOk();
});

test('operador cannot view create form', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->get('/armazens/create')->assertForbidden();
});

test('operador cannot view edit form', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($operador)->get("/armazens/{$warehouse->id}/edit")->assertForbidden();
});

test('operador cannot create a warehouse', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->post('/armazens', ['name' => 'Novo Armazém'])->assertForbidden();
});

test('operador cannot update a warehouse', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($operador)->put("/armazens/{$warehouse->id}", ['name' => 'Armazém Alterado'])->assertForbidden();
});

test('operador cannot delete a warehouse', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $warehouse = Warehouse::factory()->create();

    $this->actingAs($operador)->delete("/armazens/{$warehouse->id}")->assertForbidden();
});

test('guest is redirected to login from warehouses index', function () {
    $this->get('/armazens')->assertRedirect('/login');
});
