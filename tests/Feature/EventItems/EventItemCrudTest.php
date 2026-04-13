<?php

use App\Enums\ItemCondition;
use App\Enums\Role;
use App\Models\Event;
use App\Models\EventItem;
use App\Models\ItemCategory;
use App\Models\Supplier;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    $this->coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $this->event = Event::create([
        'name' => 'Festa', 'venue' => 'Salão',
        'starts_at' => '2026-06-01 18:00', 'ends_at' => '2026-06-02 02:00',
    ]);
    $this->category = ItemCategory::factory()->create(['name' => 'Mesas']);
});

test('coordinator adds an item to an event', function () {
    $response = $this->actingAs($this->coordinator)
        ->post("/events/{$this->event->id}/items", [
            'name' => 'Mesa redonda 1.5m',
            'item_category_id' => $this->category->id,
            'supplier_id' => null,
            'quantity' => 10,
            'rental_cost_cents' => 5000,
        ]);

    $response->assertRedirect("/events/{$this->event->id}");
    $this->assertDatabaseHas('event_items', [
        'event_id' => $this->event->id,
        'name' => 'Mesa redonda 1.5m',
        'quantity' => 10,
        'rental_cost_cents' => 5000,
        'condition' => ItemCondition::Available->value,
    ]);
});

test('item can use a supplier', function () {
    $supplier = Supplier::factory()->create();

    $this->actingAs($this->coordinator)
        ->post("/events/{$this->event->id}/items", [
            'name' => 'Cadeira Tiffany',
            'item_category_id' => $this->category->id,
            'supplier_id' => $supplier->id,
            'quantity' => 100,
            'rental_cost_cents' => 1500,
        ])->assertRedirect();

    $this->assertDatabaseHas('event_items', [
        'name' => 'Cadeira Tiffany',
        'supplier_id' => $supplier->id,
    ]);
});

test('staff cannot add items', function () {
    $staff = User::factory()->create()->assignRole(Role::Staff->value);
    $this->actingAs($staff)
        ->post("/events/{$this->event->id}/items", [
            'name' => 'X',
            'item_category_id' => $this->category->id,
            'quantity' => 1,
            'rental_cost_cents' => 0,
        ])->assertForbidden();
});

test('quantity must be at least 1', function () {
    $this->actingAs($this->coordinator)
        ->post("/events/{$this->event->id}/items", [
            'name' => 'X',
            'item_category_id' => $this->category->id,
            'quantity' => 0,
            'rental_cost_cents' => 0,
        ])->assertSessionHasErrors('quantity');
});

test('condition can advance forward', function () {
    $item = EventItem::factory()->for($this->event)->for($this->category)->create([
        'condition' => ItemCondition::Available->value,
    ]);

    $this->actingAs($this->coordinator)
        ->put("/events/{$this->event->id}/items/{$item->id}", [
            'name' => $item->name,
            'item_category_id' => $item->item_category_id,
            'supplier_id' => null,
            'quantity' => $item->quantity,
            'rental_cost_cents' => $item->rental_cost_cents,
            'condition' => ItemCondition::InUse->value,
        ])->assertRedirect();

    expect($item->fresh()->condition)->toBe(ItemCondition::InUse);
});

test('condition cannot regress from in_use to available', function () {
    $item = EventItem::factory()->for($this->event)->for($this->category)->create([
        'condition' => ItemCondition::InUse->value,
    ]);

    $this->actingAs($this->coordinator)
        ->put("/events/{$this->event->id}/items/{$item->id}", [
            'name' => $item->name,
            'item_category_id' => $item->item_category_id,
            'supplier_id' => null,
            'quantity' => $item->quantity,
            'rental_cost_cents' => $item->rental_cost_cents,
            'condition' => ItemCondition::Available->value,
        ])->assertSessionHasErrors('condition');
});

test('condition cannot regress from returned', function () {
    $item = EventItem::factory()->for($this->event)->for($this->category)->create([
        'condition' => ItemCondition::Returned->value,
    ]);

    $this->actingAs($this->coordinator)
        ->put("/events/{$this->event->id}/items/{$item->id}", [
            'name' => $item->name,
            'item_category_id' => $item->item_category_id,
            'supplier_id' => null,
            'quantity' => $item->quantity,
            'rental_cost_cents' => $item->rental_cost_cents,
            'condition' => ItemCondition::InUse->value,
        ])->assertSessionHasErrors('condition');
});

test('coordinator can delete an item', function () {
    $item = EventItem::factory()->for($this->event)->for($this->category)->create();

    $this->actingAs($this->coordinator)
        ->delete("/events/{$this->event->id}/items/{$item->id}")
        ->assertRedirect("/events/{$this->event->id}");

    $this->assertDatabaseMissing('event_items', ['id' => $item->id]);
});
