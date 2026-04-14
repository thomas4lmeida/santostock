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

test('name is required', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $category = ItemCategory::factory()->create();
    $unit = Unit::factory()->create();

    $this->actingAs($admin)->post('/produtos', [
        'item_category_id' => $category->id,
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors('name');
});

test('item_category_id is required', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($admin)->post('/produtos', [
        'name' => 'Produto Teste',
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors('item_category_id');
});

test('unit_id is required', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $category = ItemCategory::factory()->create();

    $this->actingAs($admin)->post('/produtos', [
        'name' => 'Produto Teste',
        'item_category_id' => $category->id,
    ])->assertSessionHasErrors('unit_id');
});

test('item_category_id must exist in item_categories table', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $unit = Unit::factory()->create();

    $this->actingAs($admin)->post('/produtos', [
        'name' => 'Produto Teste',
        'item_category_id' => 999999,
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors('item_category_id');
});

test('unit_id must exist in units table', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $category = ItemCategory::factory()->create();

    $this->actingAs($admin)->post('/produtos', [
        'name' => 'Produto Teste',
        'item_category_id' => $category->id,
        'unit_id' => 999999,
    ])->assertSessionHasErrors('unit_id');
});

test('duplicate name within same category is rejected', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $category = ItemCategory::factory()->create();
    $unit = Unit::factory()->create();
    Product::factory()->create(['name' => 'Produto Duplicado', 'item_category_id' => $category->id, 'unit_id' => $unit->id]);

    $this->actingAs($admin)->post('/produtos', [
        'name' => 'Produto Duplicado',
        'item_category_id' => $category->id,
        'unit_id' => $unit->id,
    ])->assertSessionHasErrors('name');
});

test('same name in different category is allowed', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $category1 = ItemCategory::factory()->create();
    $category2 = ItemCategory::factory()->create();
    $unit = Unit::factory()->create();
    Product::factory()->create(['name' => 'Produto Compartilhado', 'item_category_id' => $category1->id, 'unit_id' => $unit->id]);

    $response = $this->actingAs($admin)->post('/produtos', [
        'name' => 'Produto Compartilhado',
        'item_category_id' => $category2->id,
        'unit_id' => $unit->id,
    ]);

    $response->assertRedirect('/produtos');
    $this->assertDatabaseCount('products', 2);
});

test('name unique rule ignores current product on update', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $product = Product::factory()->create(['name' => 'Produto Existente']);

    $this->actingAs($admin)->put("/produtos/{$product->id}", [
        'name' => 'Produto Existente',
        'item_category_id' => $product->item_category_id,
        'unit_id' => $product->unit_id,
    ])->assertRedirect('/produtos');

    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Produto Existente']);
});
