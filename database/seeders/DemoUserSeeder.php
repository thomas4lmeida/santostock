<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $users = [
            Role::Administrador->value => [
                'name' => 'Administrador Demo',
                'password' => env('DEMO_ADMIN_PASSWORD', 'password'),
            ],
            Role::Operador->value => [
                'name' => 'Operador Demo',
                'password' => env('DEMO_OPERADOR_PASSWORD', 'password'),
            ],
        ];

        foreach ($users as $roleName => $attrs) {
            $user = User::firstOrCreate(
                ['email' => "{$roleName}@santostok.test"],
                [
                    'name' => $attrs['name'],
                    'password' => Hash::make($attrs['password']),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$roleName]);
        }
    }
}
