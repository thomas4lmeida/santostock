<?php

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('coordinator can delete an event', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $event = Event::create([
        'name' => 'Para excluir',
        'venue' => 'X',
        'starts_at' => '2026-05-01 10:00',
        'ends_at' => '2026-05-01 12:00',
    ]);

    $this->actingAs($coordinator)->delete("/events/{$event->id}")->assertRedirect('/events');

    $this->assertDatabaseMissing('events', ['id' => $event->id]);
});
