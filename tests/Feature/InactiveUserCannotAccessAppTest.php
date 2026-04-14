<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('inactive user gets a 403 on authenticated routes', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_active' => false,
    ]);
    $user->assignRole('operador');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertForbidden();
});

test('active user is not blocked', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
        'is_active' => true,
    ]);
    $user->assignRole('operador');

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();
});
