<?php

use App\Enums\Role;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('operador cannot list units', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->get('/unidades')->assertForbidden();
});

test('operador cannot view create form', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->get('/unidades/create')->assertForbidden();
});

test('operador cannot create a unit', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->post('/unidades', ['name' => 'X', 'abbreviation' => 'x'])->assertForbidden();
});

test('operador cannot view a unit', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($operador)->get("/unidades/{$unit->id}")->assertForbidden();
});

test('operador cannot edit a unit', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($operador)->get("/unidades/{$unit->id}/edit")->assertForbidden();
});

test('operador cannot update a unit', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($operador)->put("/unidades/{$unit->id}", ['name' => 'X', 'abbreviation' => 'x'])->assertForbidden();
});

test('operador cannot delete a unit', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($operador)->delete("/unidades/{$unit->id}")->assertForbidden();
});

test('guest is redirected to login from units index', function () {
    $this->get('/unidades')->assertRedirect('/login');
});

test('guest is redirected to login from unit show', function () {
    $unit = Unit::factory()->create();

    $this->get("/unidades/{$unit->id}")->assertRedirect('/login');
});
