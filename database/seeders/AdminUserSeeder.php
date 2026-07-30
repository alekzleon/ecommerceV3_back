<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;


class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $role = Role::query()->where('name', 'super_admin')->first();

        User::updateOrCreate(
            ['email' => 'admin@cloudishop.mx'],
            [
                'role_id' => $role?->id,
                'name' => 'Administrador',
                'username' => 'cloudishop_admin',
                'password' => Hash::make('Password'),
            ]
        );
    }
}
