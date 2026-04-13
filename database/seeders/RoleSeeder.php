<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        foreach (RoleEnum::names() as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }
    }
}
