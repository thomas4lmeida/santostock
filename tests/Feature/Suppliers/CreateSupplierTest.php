<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('coordinator can create a supplier', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);

    $response = $this->actingAs($coordinator)->post('/suppliers', [
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

test('staff cannot create supplier', function () {
    $staff = User::factory()->create()->assignRole(Role::Staff->value);
    $this->actingAs($staff)->post('/suppliers', ['name' => 'X'])->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->get('/suppliers')->assertRedirect('/login');
});
