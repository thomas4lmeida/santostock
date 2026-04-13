<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
});

test('dashboard exposes the user role to inertia for each role', function (string $role) {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.role', $role)
        );
})->with(['coordinator', 'staff', 'client']);
