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
        ->get('/suppliers')
        ->assertOk();
});

test('staff is forbidden from a coordinator-only route', function () {
    $staff = User::factory()->create();
    $staff->assignRole('staff');

    $this->actingAs($staff)
        ->get('/suppliers')
        ->assertForbidden();
});

test('client is forbidden from a coordinator-only route', function () {
    $client = User::factory()->create();
    $client->assignRole('client');

    $this->actingAs($client)
        ->get('/suppliers')
        ->assertForbidden();
});

test('guest is redirected to login from a coordinator-only route', function () {
    $this->get('/suppliers')->assertRedirect('/login');
});
