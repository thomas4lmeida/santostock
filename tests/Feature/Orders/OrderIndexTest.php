<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can view orders index', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    Order::factory()->count(3)->create();

    $this->actingAs($admin)->get('/pedidos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 3)
        );
});

test('operador can view orders index read-only', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    Order::factory()->count(2)->create();

    $this->actingAs($operador)->get('/pedidos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Orders/Index')
            ->has('orders.data', 2)
        );
});

test('index filters by status', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    Order::factory()->create(['status' => OrderStatus::Open]);
    Order::factory()->create(['status' => OrderStatus::Cancelled]);

    $this->actingAs($admin)->get('/pedidos?status=cancelled')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.status', 'cancelled')
        );
});

test('index filters by supplier', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $supplierA = Supplier::factory()->create();
    $supplierB = Supplier::factory()->create();
    $product = Product::factory()->create();
    Order::factory()->create(['supplier_id' => $supplierA->id, 'product_id' => $product->id]);
    Order::factory()->create(['supplier_id' => $supplierB->id, 'product_id' => $product->id]);

    $this->actingAs($admin)->get("/pedidos?supplier_id={$supplierA->id}")
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('orders.data', 1)
            ->where('orders.data.0.supplier_id', $supplierA->id)
        );
});
