<?php

use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

test('seeds the two application roles', function () {
    $this->seed(RoleSeeder::class);

    expect(Role::pluck('name')->sort()->values()->all())
        ->toBe(['administrador', 'operador']);
});

test('is idempotent when run twice', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(Role::count())->toBe(2);
});
