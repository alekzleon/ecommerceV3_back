<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class UserSeederRoles extends Seeder
{
    public function run(): void
    {
        $roles = [
            'super_admin',
            'admin',
            'sistemas',
            'marketing',
            'credito_cobranza',
            'cliente',
        ];

        foreach ($roles as $roleName) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                continue;
            }

            User::query()->updateOrCreate(
                ['username' => $roleName],
                [
                    'name' => ucfirst(str_replace('_', ' ', $roleName)),
                    'email' => $roleName . '@cloudishop.mx',
                    'password' => Hash::make('Password'),
                    'role_id' => $role->id,
                ]
            );
        }
    }
}
