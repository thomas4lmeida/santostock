<?php

use Database\Seeders\PermissionSeeder;
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

test('grants administrador role every Phase 5 permission', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $administrador = Role::findByName('administrador');

    expect($administrador->permissions->pluck('name')->sort()->values()->all())
        ->toBe([
            'admin.access',
            'attachments.manage',
            'attachments.view',
            'orders.view',
            'receipts.correct',
            'receipts.create',
            'receipts.view',
        ]);
});

test('grants operador a read/create subset without correct or manage permissions', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $operador = Role::findByName('operador');

    expect($operador->permissions->pluck('name')->sort()->values()->all())
        ->toBe([
            'attachments.view',
            'orders.view',
            'receipts.create',
            'receipts.view',
        ]);
});
