<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;

test('a user can be assigned a role', function () {
    $this->seed(RoleSeeder::class);
    $user = User::factory()->create();

    $user->assignRole('coordinator');

    expect($user->hasRole('coordinator'))->toBeTrue();
});
