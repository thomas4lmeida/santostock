<?php

use App\Enums\Role;
use App\Models\Event;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('coordinator creates supplier, category, then adds item visible under category group', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $event = Event::factory()->create();

    $this->actingAs($coordinator)
        ->post('/suppliers', ['name' => 'Locadora Sul'])
        ->assertRedirect('/suppliers');

    $this->actingAs($coordinator)
        ->post('/item-categories', ['name' => 'Iluminação'])
        ->assertRedirect('/item-categories');

    $supplierId = Supplier::firstWhere('name', 'Locadora Sul')->id;
    $categoryId = ItemCategory::firstWhere('name', 'Iluminação')->id;

    $this->actingAs($coordinator)
        ->post("/events/{$event->id}/items", [
            'name' => 'Refletor LED',
            'item_category_id' => $categoryId,
            'supplier_id' => $supplierId,
            'quantity' => 4,
            'rental_cost_cents' => 8000,
        ])->assertRedirect("/events/{$event->id}");

    $response = $this->actingAs($coordinator)->get("/events/{$event->id}");
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Events/Show')
        ->has('itemGroups', 1)
        ->where('itemGroups.0.category.name', 'Iluminação')
        ->has('itemGroups.0.items', 1)
        ->where('itemGroups.0.items.0.name', 'Refletor LED')
        ->where('itemGroups.0.items.0.supplier.name', 'Locadora Sul')
    );
});
