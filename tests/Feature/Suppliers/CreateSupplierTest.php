<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can create a supplier', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $response = $this->actingAs($admin)->post('/suppliers', [
        'name' => 'Fornecedor Alpha',
        'contact_name' => 'João Silva',
        'phone' => '11999998888',
        'email' => 'contato@alpha.com.br',
        'notes' => 'Entrega aos sábados',
    ]);

    $response->assertRedirect('/suppliers');
    $this->assertDatabaseHas('suppliers', [
        'name' => 'Fornecedor Alpha',
        'email' => 'contato@alpha.com.br',
    ]);
});

test('operador cannot create supplier', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $this->actingAs($operador)->post('/suppliers', ['name' => 'X'])->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->get('/suppliers')->assertRedirect('/login');
});
