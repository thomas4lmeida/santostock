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

test('admin can list products', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $this->actingAs($admin)->get('/produtos')->assertOk();
});

test('index response includes eager-loaded relations', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    Product::factory()->create();

    $this->actingAs($admin)->get('/produtos')->assertInertia(fn ($page) => $page
        ->has('products.data.0.item_category')
        ->has('products.data.0.unit')
    );
});

test('admin can view create form', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);

    $response = $this->actingAs($admin)->get('/produtos/create');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('itemCategories')
        ->has('units')
    );
});

test('admin can create a product', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $category = ItemCategory::factory()->create();
    $unit = Unit::factory()->create();

    $response = $this->actingAs($admin)->post('/produtos', [
        'name' => 'Produto Teste',
        'item_category_id' => $category->id,
        'unit_id' => $unit->id,
    ]);

    $response->assertRedirect('/produtos');
    $this->assertDatabaseHas('products', [
        'name' => 'Produto Teste',
        'item_category_id' => $category->id,
        'unit_id' => $unit->id,
    ]);
});

test('admin can view a product', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $product = Product::factory()->create();

    $response = $this->actingAs($admin)->get("/produtos/{$product->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('product.item_category')
        ->has('product.unit')
    );
});

test('admin can view edit form', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $product = Product::factory()->create();

    $response = $this->actingAs($admin)->get("/produtos/{$product->id}/edit");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->has('product')
        ->has('itemCategories')
        ->has('units')
    );
});

test('admin can update a product', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $product = Product::factory()->create(['name' => 'Produto Antigo']);
    $unit = Unit::factory()->create();

    $response = $this->actingAs($admin)->put("/produtos/{$product->id}", [
        'name' => 'Produto Novo',
        'item_category_id' => $product->item_category_id,
        'unit_id' => $unit->id,
    ]);

    $response->assertRedirect('/produtos');
    $this->assertDatabaseHas('products', ['id' => $product->id, 'name' => 'Produto Novo']);
});

test('admin can delete a product', function () {
    $admin = User::factory()->withTwoFactor()->create()->assignRole(Role::Administrador->value);
    $product = Product::factory()->create();

    $this->actingAs($admin)->delete("/produtos/{$product->id}")->assertRedirect('/produtos');

    $this->assertDatabaseMissing('products', ['id' => $product->id]);
});
