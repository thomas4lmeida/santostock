<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('coordinator can create an event', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);

    $response = $this->actingAs($coordinator)->post('/events', [
        'name' => 'Casamento Silva',
        'description' => 'Cerimônia e recepção',
        'venue' => 'Salão Azul',
        'starts_at' => '2026-05-01 18:00:00',
        'ends_at' => '2026-05-02 02:00:00',
    ]);

    $response->assertRedirect('/events');
    $this->assertDatabaseHas('events', [
        'name' => 'Casamento Silva',
        'venue' => 'Salão Azul',
    ]);
});
