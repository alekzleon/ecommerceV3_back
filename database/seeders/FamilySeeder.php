<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Family;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class FamilySeeder extends Seeder
{
    public function run(): void
    {
        $categoryFamilies = [
            [
                'category_name' => 'Computadoras',
                'families' => [
                    'Laptops',
                    'Computadoras de Escritorio',
                    'All in One',
                    'Workstations',
                ],
            ],
            [
                'category_name' => 'Smartphones',
                'families' => [
                    'Android',
                    'iOS',
                    'Gama Media',
                    'Gama Alta',
                ],
            ],
            [
                'category_name' => 'Tablets',
                'families' => [
                    'Tablets Android',
                    'iPad',
                    'Tablets para Dibujo',
                    'Tablets Infantiles',
                ],
            ],
            [
                'category_name' => 'Accesorios Tecnológicos',
                'families' => [
                    'Cargadores',
                    'Cables y Adaptadores',
                    'Fundas y Protectores',
                    'Soportes y Bases',
                ],
            ],
            [
                'category_name' => 'Audio y Video',
                'families' => [
                    'Audífonos',
                    'Bocinas',
                    'Barras de Sonido',
                    'Cámaras Web',
                ],
            ],
            [
                'category_name' => 'Gaming',
                'families' => [
                    'Consolas',
                    'Controles',
                    'Sillas Gamer',
                    'Periféricos Gamer',
                ],
            ],
            [
                'category_name' => 'Domótica',
                'families' => [
                    'Focos Inteligentes',
                    'Cámaras de Seguridad',
                    'Asistentes de Voz',
                    'Sensores Inteligentes',
                ],
            ],
            [
                'category_name' => 'Redes y Conectividad',
                'families' => [
                    'Routers',
                    'Switches',
                    'Extensores WiFi',
                    'Cables de Red',
                ],
            ],
            [
                'category_name' => 'Componentes de PC',
                'families' => [
                    'Procesadores',
                    'Tarjetas Madre',
                    'Memorias RAM',
                    'Almacenamiento',
                ],
            ],
            [
                'category_name' => 'Wearables',
                'families' => [
                    'Smartwatches',
                    'Bandas Deportivas',
                    'Lentes Inteligentes',
                    'Rastreadores GPS',
                ],
            ],
        ];

        foreach ($categoryFamilies as $group) {
            $category = Category::where('name', $group['category_name'])->first();

            if (!$category) {
                continue;
            }

            foreach ($group['families'] as $familyName) {
                Family::updateOrCreate(
                    [
                        'category_id' => $category->id,
                        'slug' => Str::slug($familyName),
                    ],
                    [
                        'name' => $familyName,
                        'is_active' => true,
                    ]
                );
            }
        }
    }
}
