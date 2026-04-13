<?php

use App\Enums\Role;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\ItemCategory;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('event show groups items by category', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $event = Event::factory()->create();
    $tables = ItemCategory::factory()->create(['name' => 'Mesas']);
    $chairs = ItemCategory::factory()->create(['name' => 'Cadeiras']);
    EventItem::factory()->for($event)->for($tables)->create(['name' => 'Mesa A']);
    EventItem::factory()->for($event)->for($tables)->create(['name' => 'Mesa B']);
    EventItem::factory()->for($event)->for($chairs)->create(['name' => 'Cadeira X']);

    $response = $this->actingAs($coordinator)->get("/events/{$event->id}");

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Events/Show')
        ->has('itemGroups', 2)
        ->where('itemGroups.0.category.name', 'Cadeiras')
        ->has('itemGroups.0.items', 1)
        ->where('itemGroups.1.category.name', 'Mesas')
        ->has('itemGroups.1.items', 2)
    );
});
