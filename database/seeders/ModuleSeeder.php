<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Module;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'dashboard',
                'display_name' => 'Dashboard',
                'description' => 'Panel principal del administrador',
                'group_key' => 'administracion',
                'sort_order' => 1,
                'route_name' => 'admin.dashboard',
                'icon' => 'fa-solid fa-gauge-high',
                'is_active' => true,
            ],
            [
                'name' => 'usuarios',
                'display_name' => 'Usuarios',
                'description' => 'Gestión de usuarios internos y clientes',
                'group_key' => 'administracion',
                'sort_order' => 2,
                'route_name' => 'admin.users.index',
                'icon' => 'fa-solid fa-users',
                'is_active' => true,
            ],
            [
                'name' => 'roles',
                'display_name' => 'Roles',
                'description' => 'Gestión de roles y asignación de módulos',
                'group_key' => 'administracion',
                'sort_order' => 3,
                'route_name' => 'admin.roles.index',
                'icon' => 'fa-solid fa-user-shield',
                'is_active' => true,
            ],
            [
                'name' => 'productos',
                'display_name' => 'Productos',
                'description' => 'Gestión de catálogo y productos',
                'group_key' => 'operacion',
                'sort_order' => 4,
                'route_name' => 'admin.products.index',
                'icon' => 'fa-solid fa-box',
                'is_active' => true,
            ],
            [
                'name' => 'pedidos',
                'display_name' => 'Pedidos',
                'description' => 'Gestión y seguimiento de pedidos',
                'group_key' => 'operacion',
                'sort_order' => 5,
                'route_name' => 'admin.orders.index',
                'icon' => 'fa-solid fa-cart-shopping',
                'is_active' => true,
            ],
            [
                'name' => 'clientes',
                'display_name' => 'Clientes',
                'description' => 'Gestión de clientes e información general',
                'group_key' => 'operacion',
                'sort_order' => 6,
                'route_name' => 'admin.customers.index',
                'icon' => 'fa-solid fa-address-book',
                'is_active' => true,
            ],
            [
                'name' => 'credito',
                'display_name' => 'Crédito',
                'description' => 'Control de crédito y validaciones',
                'group_key' => 'finanzas',
                'sort_order' => 7,
                'route_name' => 'admin.credit.index',
                'icon' => 'fa-solid fa-credit-card',
                'is_active' => true,
            ],
            [
                'name' => 'cobranza',
                'display_name' => 'Cobranza',
                'description' => 'Gestión de cartera, cobros y seguimiento',
                'group_key' => 'finanzas',
                'sort_order' => 8,
                'route_name' => 'admin.collections.index',
                'icon' => 'fa-solid fa-money-bill-wave',
                'is_active' => true,
            ],
            [
                'name' => 'marketing',
                'display_name' => 'Marketing',
                'description' => 'Herramientas comerciales y visuales',
                'group_key' => 'marketing',
                'sort_order' => 9,
                'route_name' => 'admin.marketing.index',
                'icon' => 'fa-solid fa-bullhorn',
                'is_active' => true,
            ],
            [
                'name' => 'promociones',
                'display_name' => 'Promociones',
                'description' => 'Promociones, ofertas, banners e imágenes',
                'group_key' => 'marketing',
                'sort_order' => 10,
                'route_name' => 'admin.promotions.index',
                'icon' => 'fa-solid fa-tags',
                'is_active' => true,
            ],
            [
                'name' => 'banners',
                'display_name' => 'Banners',
                'description' => 'Banners principales del ecommerce',
                'group_key' => 'marketing',
                'sort_order' => 11,
                'route_name' => 'admin.banners.index',
                'icon' => 'fa-solid fa-images',
                'is_active' => true,
            ],
            [
                'name' => 'logs',
                'display_name' => 'Logs',
                'description' => 'Registros de actividad de usuarios y clientes',
                'group_key' => 'control',
                'sort_order' => 12,
                'route_name' => 'admin.logs.index',
                'icon' => 'fa-solid fa-file-lines',
                'is_active' => true,
            ],
            [
                'name' => 'sincronizacion',
                'display_name' => 'Sincronización',
                'description' => 'Monitoreo y control de procesos de sincronización',
                'group_key' => 'control',
                'sort_order' => 13,
                'route_name' => 'admin.sync.index',
                'icon' => 'fa-solid fa-rotate',
                'is_active' => true,
            ],
            [
                'name' => 'configuracion_ecommerce',
                'display_name' => 'Configuración Ecommerce',
                'description' => 'Ajustes generales del ecommerce',
                'group_key' => 'sistema',
                'sort_order' => 14,
                'route_name' => 'admin.settings.index',
                'icon' => 'fa-solid fa-gear',
                'is_active' => true,
            ],
            [
                'name' => 'front_ecommerce',
                'display_name' => 'Front Ecommerce',
                'description' => 'Acceso al ecosistema de compra del cliente',
                'group_key' => 'front',
                'sort_order' => 15,
                'route_name' => 'web.home',
                'icon' => 'fa-solid fa-store',
                'is_active' => true,
            ],
        ];

        foreach ($modules as $module) {
            Module::updateOrCreate(
                ['name' => $module['name']],
                $module
            );
        }
    }
}
