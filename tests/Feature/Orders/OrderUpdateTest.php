<?php

use App\Enums\Role;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can update an order', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $order = Order::factory()->create(['ordered_quantity' => 10, 'notes' => 'antigo']);

    $this->actingAs($admin)->put("/pedidos/{$order->id}", [
        'supplier_id' => $order->supplier_id,
        'product_id' => $order->product_id,
        'ordered_quantity' => 30,
        'notes' => 'atualizado',
    ])->assertRedirect('/pedidos');

    $this->assertDatabaseHas('orders', [
        'id' => $order->id,
        'ordered_quantity' => 30,
        'notes' => 'atualizado',
    ]);
});

test('operador cannot update an order', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $order = Order::factory()->create();

    $this->actingAs($operador)->put("/pedidos/{$order->id}", [
        'supplier_id' => $order->supplier_id,
        'product_id' => $order->product_id,
        'ordered_quantity' => 5,
    ])->assertForbidden();
});

test('quantity cannot be changed when order has receipts', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $order = Order::factory()->create(['ordered_quantity' => 10]);
    Receipt::factory()->create(['order_id' => $order->id, 'quantity' => 3]);

    $this->actingAs($admin)->put("/pedidos/{$order->id}", [
        'supplier_id' => $order->supplier_id,
        'product_id' => $order->product_id,
        'ordered_quantity' => 99,
    ])->assertSessionHasErrors('ordered_quantity');

    expect($order->fresh()->ordered_quantity)->toBe(10);
});

test('notes and supplier can still be updated when receipts exist', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $order = Order::factory()->create(['ordered_quantity' => 10, 'notes' => 'old']);
    Receipt::factory()->create(['order_id' => $order->id]);

    $this->actingAs($admin)->put("/pedidos/{$order->id}", [
        'supplier_id' => $order->supplier_id,
        'product_id' => $order->product_id,
        'ordered_quantity' => 10,
        'notes' => 'new',
    ])->assertRedirect('/pedidos');

    expect($order->fresh()->notes)->toBe('new');
});

test('admin can delete open order without receipts', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $order = Order::factory()->open()->create();

    $this->actingAs($admin)->delete("/pedidos/{$order->id}")
        ->assertRedirect('/pedidos');

    $this->assertDatabaseMissing('orders', ['id' => $order->id]);
});

test('cannot delete order that has receipts', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $order = Order::factory()->open()->create();
    Receipt::factory()->create(['order_id' => $order->id]);

    $this->actingAs($admin)->delete("/pedidos/{$order->id}")
        ->assertStatus(422);
});

test('cannot delete non-open order', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $order = Order::factory()->cancelled()->create();

    $this->actingAs($admin)->delete("/pedidos/{$order->id}")
        ->assertStatus(422);

    $this->assertDatabaseHas('orders', ['id' => $order->id]);
});

test('admin can view an order', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $order = Order::factory()->create();

    $this->actingAs($admin)->get("/pedidos/{$order->id}")->assertOk();
});

test('operador can view an order', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $order = Order::factory()->create();

    $this->actingAs($operador)->get("/pedidos/{$order->id}")->assertOk();
});
