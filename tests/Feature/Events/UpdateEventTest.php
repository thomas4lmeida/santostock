<?php

use App\Enums\Role;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('coordinator can update an event', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);
    $event = Event::create([
        'name' => 'Antigo',
        'venue' => 'Salão A',
        'starts_at' => '2026-05-01 18:00:00',
        'ends_at' => '2026-05-02 02:00:00',
    ]);

    $response = $this->actingAs($coordinator)->put("/events/{$event->id}", [
        'name' => 'Atualizado',
        'venue' => 'Salão B',
        'starts_at' => '2026-05-01 18:00:00',
        'ends_at' => '2026-05-02 02:00:00',
    ]);

    $response->assertRedirect('/events');
    $this->assertDatabaseHas('events', ['id' => $event->id, 'name' => 'Atualizado', 'venue' => 'Salão B']);
});
