<?php

use App\Models\User;
use Database\Seeders\DemoUserSeeder;
use Database\Seeders\RoleSeeder;

test('seeds one user per role with predictable emails', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(DemoUserSeeder::class);

    foreach (['administrador', 'operador'] as $role) {
        $user = User::where('email', "{$role}@santostok.test")->first();
        expect($user)->not->toBeNull()
            ->and($user->hasRole($role))->toBeTrue();
    }

    expect(User::count())->toBe(2);
});

test('is idempotent when run twice', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(DemoUserSeeder::class);
    $this->seed(DemoUserSeeder::class);

    expect(User::count())->toBe(2);
});
