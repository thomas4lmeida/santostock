<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('rejects event where ends_at is before starts_at', function () {
    $coordinator = User::factory()->create()->assignRole(Role::Coordinator->value);

    $response = $this->actingAs($coordinator)->post('/events', [
        'name' => 'Evento inválido',
        'venue' => 'Salão X',
        'starts_at' => '2026-05-02 10:00:00',
        'ends_at' => '2026-05-01 10:00:00',
    ]);

    $response->assertSessionHasErrors('ends_at');
    $this->assertDatabaseCount('events', 0);
});
