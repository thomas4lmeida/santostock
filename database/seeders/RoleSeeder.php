<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PermissionSeeder::class);

        foreach (RoleEnum::names() as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        Role::findByName(RoleEnum::Administrador->value)
            ->syncPermissions(PermissionSeeder::PERMISSIONS);

        Role::findByName(RoleEnum::Operador->value)
            ->syncPermissions(array_values(array_diff(
                PermissionSeeder::PERMISSIONS,
                ['admin.access', 'receipts.correct', 'attachments.manage'],
            )));
    }
}
