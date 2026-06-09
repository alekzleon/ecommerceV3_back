<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => '01', 'name' => 'Computadoras'],
            ['code' => '02', 'name' => 'Smartphones'],
            ['code' => '03', 'name' => 'Tablets'],
            ['code' => '04', 'name' => 'Accesorios Tecnológicos'],
            ['code' => '05', 'name' => 'Audio y Video'],
            ['code' => '06', 'name' => 'Gaming'],
            ['code' => '07', 'name' => 'Domótica'],
            ['code' => '08', 'name' => 'Redes y Conectividad'],
            ['code' => '09', 'name' => 'Componentes de PC'],
            ['code' => '10', 'name' => 'Wearables'],
        ];

        foreach ($categories as $category) {
            Category::updateOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'slug' => Str::slug($category['name']),
                    'is_active' => true,
                ]
            );
        }
    }
}
