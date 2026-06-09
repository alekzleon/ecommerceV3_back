<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Module;
use App\Models\Role;

class RoleModuleSeeder extends Seeder
{
    public function run(): void
    {
        $map = [
            'super_admin' => [
                'dashboard',
                'usuarios',
                'roles',
                'productos',
                'pedidos',
                'clientes',
                'credito',
                'cobranza',
                'marketing',
                'promociones',
                'banners',
                'logs',
                'sincronizacion',
                'configuracion_ecommerce',
                'front_ecommerce',
            ],
            'admin' => [
                'dashboard',
                'usuarios',
                'roles',
                'productos',
                'pedidos',
                'clientes',
                'credito',
                'cobranza',
                'marketing',
                'promociones',
                'banners',
                'logs',
                'sincronizacion',
                'configuracion_ecommerce',
            ],
            'sistemas' => [
                'dashboard',
                'usuarios',
                'roles',
                'productos',
                'pedidos',
                'clientes',
                'credito',
                'cobranza',
                'marketing',
                'promociones',
                'banners',
                'logs',
                'sincronizacion',
                'configuracion_ecommerce',
            ],
            'marketing' => [
                'marketing',
                'promociones',
                'banners',
            ],
            'credito_cobranza' => [
                'clientes',
                'credito',
                'cobranza',
            ],
            'cliente' => [
                'front_ecommerce',
            ],
        ];

        foreach ($map as $roleName => $moduleNames) {
            $role = Role::where('name', $roleName)->first();

            if (!$role) {
                continue;
            }

            $moduleIds = Module::whereIn('name', $moduleNames)->pluck('id')->toArray();

            $role->modules()->sync($moduleIds);
        }
    }
}
