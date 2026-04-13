<?php

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('staff cannot access events index', function () {
    $staff = User::factory()->create()->assignRole(Role::Staff->value);
    $this->actingAs($staff)->get('/events')->assertForbidden();
});

test('client cannot access events index', function () {
    $client = User::factory()->create()->assignRole(Role::Client->value);
    $this->actingAs($client)->get('/events')->assertForbidden();
});

test('guest is redirected to login', function () {
    $this->get('/events')->assertRedirect('/login');
});

test('staff cannot create event', function () {
    $staff = User::factory()->create()->assignRole(Role::Staff->value);
    $this->actingAs($staff)->post('/events', [
        'name' => 'X', 'venue' => 'Y',
        'starts_at' => '2026-05-01 10:00', 'ends_at' => '2026-05-01 12:00',
    ])->assertForbidden();
});
