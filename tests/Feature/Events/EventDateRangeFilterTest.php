<?php

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('filters events by date range', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);

    Event::create(['name' => 'Early', 'venue' => 'A', 'starts_at' => '2026-04-01 10:00', 'ends_at' => '2026-04-02 10:00']);
    Event::create(['name' => 'Mid', 'venue' => 'B', 'starts_at' => '2026-05-15 10:00', 'ends_at' => '2026-05-16 10:00']);
    Event::create(['name' => 'Late', 'venue' => 'C', 'starts_at' => '2026-06-20 10:00', 'ends_at' => '2026-06-21 10:00']);

    $this->actingAs($coordinator)->get('/events?from=2026-05-01&to=2026-05-31')
        ->assertInertia(fn ($p) => $p->has('events.data', 1)->where('events.data.0.name', 'Mid'));
});
