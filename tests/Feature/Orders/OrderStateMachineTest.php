<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Receipt;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

function admin(): User
{
    return User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
}

test('admin can cancel an open order with no receipts', function () {
    $order = Order::factory()->open()->create();

    $this->actingAs(admin())->post("/pedidos/{$order->id}/cancelar")->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

test('cancel is blocked when receipts exist', function () {
    $order = Order::factory()->partiallyReceived()->create();
    Receipt::factory()->create(['order_id' => $order->id]);

    $this->actingAs(admin())->post("/pedidos/{$order->id}/cancelar")->assertStatus(422);

    expect($order->fresh()->status)->toBe(OrderStatus::PartiallyReceived);
});

test('cancel is blocked from terminal state', function () {
    $order = Order::factory()->fullyReceived()->create();

    $this->actingAs(admin())->post("/pedidos/{$order->id}/cancelar")->assertStatus(422);
});

test('operador cannot cancel an order', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $order = Order::factory()->open()->create();

    $this->actingAs($operador)->post("/pedidos/{$order->id}/cancelar")->assertForbidden();
});

test('admin can close short a partially received order', function () {
    $order = Order::factory()->partiallyReceived()->create();

    $this->actingAs(admin())->post("/pedidos/{$order->id}/encerrar-saldo-curto")->assertRedirect();

    expect($order->fresh()->status)->toBe(OrderStatus::ClosedShort);
});

test('close short is blocked from Open state', function () {
    $order = Order::factory()->open()->create();

    $this->actingAs(admin())->post("/pedidos/{$order->id}/encerrar-saldo-curto")->assertStatus(422);
});

test('operador cannot close short an order', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $order = Order::factory()->partiallyReceived()->create();

    $this->actingAs($operador)->post("/pedidos/{$order->id}/encerrar-saldo-curto")->assertForbidden();
});
