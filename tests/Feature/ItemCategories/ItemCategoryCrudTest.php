<?php

use App\Enums\Role;
use App\Models\ItemCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('coordinator can create a category', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);

    $response = $this->actingAs($coordinator)->post('/item-categories', [
        'name' => 'Mesas',
    ]);

    $response->assertRedirect('/item-categories');
    $this->assertDatabaseHas('item_categories', ['name' => 'Mesas']);
});

test('staff cannot create category', function () {
    $staff = User::factory()->create()->assignRole(Role::Staff->value);
    $this->actingAs($staff)->post('/item-categories', ['name' => 'X'])->assertForbidden();
});

test('coordinator can update a category', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $category = ItemCategory::factory()->create(['name' => 'Antigo']);

    $this->actingAs($coordinator)->put("/item-categories/{$category->id}", [
        'name' => 'Novo',
    ])->assertRedirect('/item-categories');

    expect($category->fresh()->name)->toBe('Novo');
});

test('coordinator can delete a category', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $category = ItemCategory::factory()->create();

    $this->actingAs($coordinator)->delete("/item-categories/{$category->id}")
        ->assertRedirect('/item-categories');

    $this->assertDatabaseMissing('item_categories', ['id' => $category->id]);
});

test('name is required', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $this->actingAs($coordinator)->post('/item-categories', ['name' => ''])
        ->assertSessionHasErrors('name');
});

test('guest is redirected to login', function () {
    $this->get('/item-categories')->assertRedirect('/login');
});
