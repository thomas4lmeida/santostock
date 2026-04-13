<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Display names per role (pt-BR).
     */
    private const NAMES = [
        'coordinator' => 'Coordenador Demo',
        'staff' => 'Equipe Demo',
        'client' => 'Cliente Demo',
    ];

    public function run(): void
    {
        foreach (Role::cases() as $role) {
            $user = User::firstOrCreate(
                ['email' => "{$role->value}@santostok.test"],
                [
                    'name' => self::NAMES[$role->value],
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ],
            );

            $user->syncRoles([$role->value]);
        }
    }
}
