<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;

class AssignSuperAdminToExistingUserSeeder extends Seeder
{
        public function run(): void
        {
            $email = 'admin@cloudishop.mx';

            $user = User::where('email', $email)->first();
            $role = Role::where('name', 'super_admin')->first();

            if ($user && $role) {
                $user->update([
                    'role_id' => $role->id,
                ]);
            }
        }
}
