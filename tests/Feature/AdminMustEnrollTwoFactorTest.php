<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('administrador without 2FA is redirected to security settings', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => null,
    ]);
    $admin->assignRole('administrador');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertRedirect('/settings/security');
});

test('administrador with 2FA is not redirected', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => encrypt('fake-secret'),
    ]);
    $admin->assignRole('administrador');

    $this->actingAs($admin)
        ->get('/dashboard')
        ->assertOk();
});

test('operador without 2FA is never redirected', function () {
    $operador = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => null,
    ]);
    $operador->assignRole('operador');

    $this->actingAs($operador)
        ->get('/dashboard')
        ->assertOk();
});

test('administrador without 2FA is not looped when reaching security settings', function () {
    $admin = User::factory()->create([
        'email_verified_at' => now(),
        'two_factor_secret' => null,
    ]);
    $admin->assignRole('administrador');

    $response = $this->actingAs($admin)->get('/settings/security');

    // The page itself sits behind password.confirm, but it must not be
    // redirected back to /settings/security by our 2FA middleware.
    expect($response->headers->get('Location'))->not->toBe(url('/settings/security'));
});
