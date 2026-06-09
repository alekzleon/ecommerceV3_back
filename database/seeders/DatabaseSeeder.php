<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();
        $this->call([
            AdminUserSeeder::class,
            RolesSeeder::class,
            CategorySeeder::class,
            FamilySeeder::class,
            ProductSeeder::class,
            RoleSeeder::class,
            ModuleSeeder::class,
            RoleModuleSeeder::class,
            AssignSuperAdminToExistingUserSeeder::class,
            UserSeederRoles::class, 
            CustomerSeeder::class,
        ]);
    }
}
