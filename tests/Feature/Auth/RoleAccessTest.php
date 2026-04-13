<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('coordinator can access a coordinator-only route', function () {
    $coordinator = User::factory()->create();
    $coordinator->assignRole('coordinator');

    $this->actingAs($coordinator)
        ->get('/events')
        ->assertOk();
});

test('staff is forbidden from a coordinator-only route', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff)
        ->get('/events')
        ->assertForbidden();
});

test('client is forbidden from a coordinator-only route', function () {
    $client = User::factory()->create();
    $client->assignRole('client');

    $this->actingAs($client)
        ->get('/events')
        ->assertForbidden();
});

test('guest is redirected to login from a coordinator-only route', function () {
    $this->get('/events')->assertRedirect('/login');
});
