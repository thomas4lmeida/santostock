<?php

use App\Enums\Role;
use App\Models\ItemCategory;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('operador can list products', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->get('/produtos')->assertOk();
});

test('operador can view a product', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $product = Product::factory()->create();

    $this->actingAs($operador)->get("/produtos/{$product->id}")->assertOk();
});

test('operador cannot view create form', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);

    $this->actingAs($operador)->get('/produtos/create')->assertForbidden();
});

test('operador cannot view edit form', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $product = Product::factory()->create();

    $this->actingAs($operador)->get("/produtos/{$product->id}/edit")->assertForbidden();
});

test('operador cannot create a product', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $category = ItemCategory::factory()->create();
    $unit = Unit::factory()->create();

    $this->actingAs($operador)->post('/produtos', [
        'name' => 'Novo Produto',
        'item_category_id' => $category->id,
        'unit_id' => $unit->id,
    ])->assertForbidden();
});

test('operador cannot update a product', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $product = Product::factory()->create();

    $this->actingAs($operador)->put("/produtos/{$product->id}", [
        'name' => 'Produto Alterado',
        'item_category_id' => $product->item_category_id,
        'unit_id' => $product->unit_id,
    ])->assertForbidden();
});

test('operador cannot delete a product', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $product = Product::factory()->create();

    $this->actingAs($operador)->delete("/produtos/{$product->id}")->assertForbidden();
});

test('guest is redirected to login from products index', function () {
    $this->get('/produtos')->assertRedirect('/login');
});
