<?php

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('index page lists existing events', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    Event::create([
        'name' => 'Festa Junina',
        'venue' => 'Quadra Central',
        'starts_at' => '2026-06-15 18:00:00',
        'ends_at' => '2026-06-15 23:00:00',
    ]);

    $response = $this->actingAs($coordinator)->get('/events');

    $response->assertOk();
    $response->assertInertia(
        fn ($page) => $page
            ->component('Events/Index')
            ->has('events.data', 1)
            ->where('events.data.0.name', 'Festa Junina')
    );
});
