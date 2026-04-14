<?php

use App\Enums\Role;
use App\Models\ItemCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('admin can create a category', function () {
    $admin = User::factory()->create()->assignRole(Role::Administrador->value);

    $response = $this->actingAs($admin)->post('/item-categories', [
        'name' => 'Mesas',
    ]);

    $response->assertRedirect('/item-categories');
    $this->assertDatabaseHas('item_categories', ['name' => 'Mesas']);
});

test('operador cannot create category', function () {
    $operador = User::factory()->create()->assignRole(Role::Operador->value);
    $this->actingAs($operador)->post('/item-categories', ['name' => 'X'])->assertForbidden();
});

test('admin can update a category', function () {
    $admin = User::factory()->create()->assignRole(Role::Administrador->value);
    $category = ItemCategory::factory()->create(['name' => 'Antigo']);

    $this->actingAs($admin)->put("/item-categories/{$category->id}", [
        'name' => 'Novo',
    ])->assertRedirect('/item-categories');

    expect($category->fresh()->name)->toBe('Novo');
});

test('admin can delete a category', function () {
    $admin = User::factory()->create()->assignRole(Role::Administrador->value);
    $category = ItemCategory::factory()->create();

    $this->actingAs($admin)->delete("/item-categories/{$category->id}")
        ->assertRedirect('/item-categories');

    $this->assertDatabaseMissing('item_categories', ['id' => $category->id]);
});

test('name is required', function () {
    $admin = User::factory()->create()->assignRole(Role::Administrador->value);
    $this->actingAs($admin)->post('/item-categories', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('guest is redirected to login', function () {
    $this->get('/item-categories')->assertRedirect('/login');
});
