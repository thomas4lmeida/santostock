<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('administrador can access an administrador-only route', function () {
    $admin = User::factory()->create();
    $admin->assignRole('administrador');

    $this->actingAs($admin)
        ->get('/suppliers')
        ->assertOk();
});

test('operador is forbidden from an administrador-only route', function () {
    $operador = User::factory()->create();
    $operador->assignRole('operador');

    $this->actingAs($operador)
        ->get('/suppliers')
        ->assertForbidden();
});

test('guest is redirected to login from an administrador-only route', function () {
    $this->get('/suppliers')->assertRedirect('/login');
});
