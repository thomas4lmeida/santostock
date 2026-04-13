<?php

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Carbon::setTestNow('2026-05-15 12:00:00');
});

test('filters events by status', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);

    Event::create(['name' => 'Past', 'venue' => 'A', 'starts_at' => '2026-04-01 10:00', 'ends_at' => '2026-04-02 10:00']);
    Event::create(['name' => 'Ongoing', 'venue' => 'B', 'starts_at' => '2026-05-14 10:00', 'ends_at' => '2026-05-16 10:00']);
    Event::create(['name' => 'Upcoming', 'venue' => 'C', 'starts_at' => '2026-06-01 10:00', 'ends_at' => '2026-06-02 10:00']);

    $this->actingAs($coordinator)->get('/events?status=upcoming')
        ->assertInertia(fn ($p) => $p->has('events.data', 1)->where('events.data.0.name', 'Upcoming'));

    $this->actingAs($coordinator)->get('/events?status=ongoing')
        ->assertInertia(fn ($p) => $p->has('events.data', 1)->where('events.data.0.name', 'Ongoing'));

    $this->actingAs($coordinator)->get('/events?status=past')
        ->assertInertia(fn ($p) => $p->has('events.data', 1)->where('events.data.0.name', 'Past'));
});
