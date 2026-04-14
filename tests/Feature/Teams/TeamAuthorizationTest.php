<?php

use App\Enums\Role;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('operador cannot list teams', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->get('/equipes')->assertForbidden();
});

test('operador cannot view create form', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->get('/equipes/create')->assertForbidden();
});

test('operador cannot create a team', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->post('/equipes', ['name' => 'X'])->assertForbidden();
});

test('operador cannot view a team', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $team = Team::factory()->create();

    $this->actingAs($operador)->get("/equipes/{$team->id}")->assertForbidden();
});

test('operador cannot edit a team', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $team = Team::factory()->create();

    $this->actingAs($operador)->get("/equipes/{$team->id}/edit")->assertForbidden();
});

test('operador cannot update a team', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $team = Team::factory()->create();

    $this->actingAs($operador)->put("/equipes/{$team->id}", ['name' => 'X'])->assertForbidden();
});

test('operador cannot delete a team', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $team = Team::factory()->create();

    $this->actingAs($operador)->delete("/equipes/{$team->id}")->assertForbidden();
});

test('guest is redirected to login from teams index', function () {
    $this->get('/equipes')->assertRedirect('/login');
});

test('guest is redirected to login from team show', function () {
    $team = Team::factory()->create();

    $this->get("/equipes/{$team->id}")->assertRedirect('/login');
});
