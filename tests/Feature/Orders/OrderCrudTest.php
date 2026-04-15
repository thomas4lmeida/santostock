<?php

use App\Enums\Role;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can create an order', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    $response = $this->actingAs($admin)->post('/pedidos', [
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'ordered_quantity' => 25,
        'notes' => 'Entrega até sexta-feira.',
    ]);

    $response->assertRedirect('/pedidos');
    $this->assertDatabaseHas('orders', [
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'ordered_quantity' => 25,
        'status' => 'open',
        'created_by_user_id' => $admin->id,
    ]);
});

test('operador cannot create order', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $this->actingAs($operador)->post('/pedidos', [
        'supplier_id' => 1,
        'product_id' => 1,
        'ordered_quantity' => 1,
    ])->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->get('/pedidos')->assertRedirect('/login');
});

test('store validates required fields', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->post('/pedidos', [])
        ->assertSessionHasErrors(['supplier_id', 'product_id', 'ordered_quantity']);
});

test('store rejects zero or negative quantity', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $supplier = Supplier::factory()->create();
    $product = Product::factory()->create();

    $this->actingAs($admin)->post('/pedidos', [
        'supplier_id' => $supplier->id,
        'product_id' => $product->id,
        'ordered_quantity' => 0,
    ])->assertSessionHasErrors('ordered_quantity');
});

test('store rejects non-existent supplier or product', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->post('/pedidos', [
        'supplier_id' => 999,
        'product_id' => 999,
        'ordered_quantity' => 5,
    ])->assertSessionHasErrors(['supplier_id', 'product_id']);
});
