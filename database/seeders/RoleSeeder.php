<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'super_admin',
                'display_name' => 'Super Admin',
                'description' => 'Acceso total al sistema',
                'is_active' => true,
            ],
            [
                'name' => 'admin',
                'display_name' => 'Admin',
                'description' => 'Operación general del negocio y administración interna',
                'is_active' => true,
            ],
            [
                'name' => 'sistemas',
                'display_name' => 'Sistemas',
                'description' => 'Soporte técnico, incidencias y operación técnica del administrador',
                'is_active' => true,
            ],
            [
                'name' => 'marketing',
                'display_name' => 'Marketing',
                'description' => 'Gestión de marketing, promociones y recursos visuales',
                'is_active' => true,
            ],
            [
                'name' => 'credito_cobranza',
                'display_name' => 'Crédito / Cobranza',
                'description' => 'Gestión de clientes, crédito y cobranza',
                'is_active' => true,
            ],
            [
                'name' => 'cliente',
                'display_name' => 'Cliente',
                'description' => 'Usuario comprador del ecommerce',
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            Role::updateOrCreate(
                ['name' => $role['name']],
                $role
            );
        }
    }
}
