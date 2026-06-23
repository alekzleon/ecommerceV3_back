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

            $email = $roleName . '@cloudishop.mx';
            $user = User::query()
                ->where('username', $roleName)
                ->orWhere('email', $email)
                ->first();

            if (!$user) {
                $user = new User([
                    'username' => $roleName,
                    'email' => $email,
                ]);
            }

            $emailBelongsToAnotherUser = User::query()
                ->where('email', $email)
                ->whereKeyNot($user->getKey())
                ->exists();

            $user->fill([
                'name' => ucfirst(str_replace('_', ' ', $roleName)),
                'username' => $roleName,
                'password' => Hash::make('Password'),
                'role_id' => $role->id,
            ]);

            if (!$emailBelongsToAnotherUser) {
                $user->email = $email;
            }

            $user->save();
        }
    }
}
