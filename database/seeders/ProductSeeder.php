<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $imagePath = 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?auto=format&fit=crop&w=1200&q=80';

        $products = [
            ['category' => 'Computadoras', 'family' => 'Laptops', 'name' => 'Laptop NovaBook Pro 14 Core i7 16GB 512GB', 'brand' => 'NovaTech', 'price' => 24999.00, 'keyword' => 'laptop productividad'],
            ['category' => 'Computadoras', 'family' => 'Laptops', 'name' => 'Laptop AeroLite 15 Ryzen 7 16GB 1TB', 'brand' => 'AeroByte', 'price' => 22999.00, 'keyword' => 'laptop ligera'],
            ['category' => 'Computadoras', 'family' => 'Computadoras de Escritorio', 'name' => 'PC de Escritorio Atlas i5 16GB 512GB', 'brand' => 'Atlas PC', 'price' => 15999.00, 'keyword' => 'computadora escritorio'],
            ['category' => 'Computadoras', 'family' => 'All in One', 'name' => 'All in One Vision 24 Full HD 8GB 256GB', 'brand' => 'VisionTek', 'price' => 13499.00, 'keyword' => 'all in one'],
            ['category' => 'Computadoras', 'family' => 'Workstations', 'name' => 'Workstation RenderMax Ryzen 9 32GB RTX 4060', 'brand' => 'RenderMax', 'price' => 38999.00, 'keyword' => 'workstation diseño'],
            ['category' => 'Smartphones', 'family' => 'Android', 'name' => 'Smartphone Orion X 128GB 5G', 'brand' => 'Orion Mobile', 'price' => 7999.00, 'keyword' => 'android 5g'],
            ['category' => 'Smartphones', 'family' => 'Android', 'name' => 'Smartphone Pixelar A7 256GB AMOLED', 'brand' => 'Pixelar', 'price' => 10999.00, 'keyword' => 'smartphone android'],
            ['category' => 'Smartphones', 'family' => 'iOS', 'name' => 'iPhone 15 128GB Azul', 'brand' => 'Apple', 'price' => 18999.00, 'keyword' => 'iphone ios'],
            ['category' => 'Smartphones', 'family' => 'Gama Media', 'name' => 'Smartphone Terra M50 128GB Dual SIM', 'brand' => 'Terra Mobile', 'price' => 4999.00, 'keyword' => 'gama media'],
            ['category' => 'Smartphones', 'family' => 'Gama Alta', 'name' => 'Smartphone Ultra Z 512GB Cámara 108MP', 'brand' => 'Zenit', 'price' => 21999.00, 'keyword' => 'gama alta'],
            ['category' => 'Tablets', 'family' => 'Tablets Android', 'name' => 'Tablet TabPro 10 Android 128GB', 'brand' => 'TabPro', 'price' => 6499.00, 'keyword' => 'tablet android'],
            ['category' => 'Tablets', 'family' => 'Tablets Android', 'name' => 'Tablet EduTab 8 64GB WiFi', 'brand' => 'EduTab', 'price' => 3299.00, 'keyword' => 'tablet wifi'],
            ['category' => 'Tablets', 'family' => 'iPad', 'name' => 'iPad 10.9 64GB WiFi Plata', 'brand' => 'Apple', 'price' => 8999.00, 'keyword' => 'ipad'],
            ['category' => 'Tablets', 'family' => 'Tablets para Dibujo', 'name' => 'Tableta Gráfica SketchPad 12 Pro', 'brand' => 'SketchPad', 'price' => 5799.00, 'keyword' => 'tablet dibujo'],
            ['category' => 'Tablets', 'family' => 'Tablets Infantiles', 'name' => 'Tablet Kids Safe 7 Funda Antigolpes', 'brand' => 'KidsTech', 'price' => 2499.00, 'keyword' => 'tablet infantil'],
            ['category' => 'Accesorios Tecnológicos', 'family' => 'Cargadores', 'name' => 'Cargador USB-C PowerFast 65W', 'brand' => 'PowerFast', 'price' => 699.00, 'keyword' => 'cargador usb c'],
            ['category' => 'Accesorios Tecnológicos', 'family' => 'Cargadores', 'name' => 'Base de Carga Inalámbrica Qi 15W', 'brand' => 'Voltix', 'price' => 549.00, 'keyword' => 'carga inalámbrica'],
            ['category' => 'Accesorios Tecnológicos', 'family' => 'Cables y Adaptadores', 'name' => 'Cable USB-C a USB-C 100W 2m', 'brand' => 'LinkPro', 'price' => 299.00, 'keyword' => 'cable usb c'],
            ['category' => 'Accesorios Tecnológicos', 'family' => 'Fundas y Protectores', 'name' => 'Funda Magnética para Smartphone 6.7', 'brand' => 'ShieldCase', 'price' => 399.00, 'keyword' => 'funda protector'],
            ['category' => 'Accesorios Tecnológicos', 'family' => 'Soportes y Bases', 'name' => 'Soporte Ajustable de Aluminio para Laptop', 'brand' => 'DeskFit', 'price' => 849.00, 'keyword' => 'soporte laptop'],
            ['category' => 'Audio y Video', 'family' => 'Audífonos', 'name' => 'Audífonos SoundAir Pro ANC', 'brand' => 'SoundAir', 'price' => 2799.00, 'keyword' => 'audífonos bluetooth'],
            ['category' => 'Audio y Video', 'family' => 'Audífonos', 'name' => 'Headset Studio USB-C con Micrófono', 'brand' => 'StudioWave', 'price' => 1299.00, 'keyword' => 'headset micrófono'],
            ['category' => 'Audio y Video', 'family' => 'Bocinas', 'name' => 'Bocina Bluetooth Boom Mini 20W', 'brand' => 'BoomTech', 'price' => 999.00, 'keyword' => 'bocina bluetooth'],
            ['category' => 'Audio y Video', 'family' => 'Barras de Sonido', 'name' => 'Barra de Sonido CinemaBar 2.1', 'brand' => 'CinemaBar', 'price' => 3499.00, 'keyword' => 'barra sonido'],
            ['category' => 'Audio y Video', 'family' => 'Cámaras Web', 'name' => 'Cámara Web FocusCam Full HD 1080p', 'brand' => 'FocusCam', 'price' => 1199.00, 'keyword' => 'cámara web'],
            ['category' => 'Gaming', 'family' => 'Consolas', 'name' => 'Consola GameBox Series S 512GB', 'brand' => 'GameBox', 'price' => 7499.00, 'keyword' => 'consola gaming'],
            ['category' => 'Gaming', 'family' => 'Consolas', 'name' => 'Consola PlayStation 5 Slim 1TB', 'brand' => 'Sony', 'price' => 11999.00, 'keyword' => 'playstation'],
            ['category' => 'Gaming', 'family' => 'Controles', 'name' => 'Control Inalámbrico ProPad RGB', 'brand' => 'ProPad', 'price' => 1299.00, 'keyword' => 'control gamer'],
            ['category' => 'Gaming', 'family' => 'Sillas Gamer', 'name' => 'Silla Gamer ErgoRush Negra', 'brand' => 'ErgoRush', 'price' => 3999.00, 'keyword' => 'silla gamer'],
            ['category' => 'Gaming', 'family' => 'Periféricos Gamer', 'name' => 'Kit Gamer Teclado Mouse y Mousepad RGB', 'brand' => 'RGBForce', 'price' => 1599.00, 'keyword' => 'periféricos gamer'],
            ['category' => 'Domótica', 'family' => 'Focos Inteligentes', 'name' => 'Foco Inteligente WiFi RGB E27', 'brand' => 'CasaSmart', 'price' => 249.00, 'keyword' => 'foco inteligente'],
            ['category' => 'Domótica', 'family' => 'Focos Inteligentes', 'name' => 'Tira LED Inteligente RGB 5m', 'brand' => 'CasaSmart', 'price' => 599.00, 'keyword' => 'tira led'],
            ['category' => 'Domótica', 'family' => 'Cámaras de Seguridad', 'name' => 'Cámara IP Vigil 360 Interior', 'brand' => 'VigilTech', 'price' => 899.00, 'keyword' => 'cámara seguridad'],
            ['category' => 'Domótica', 'family' => 'Asistentes de Voz', 'name' => 'Asistente de Voz HomeHub Mini', 'brand' => 'HomeHub', 'price' => 1199.00, 'keyword' => 'asistente voz'],
            ['category' => 'Domótica', 'family' => 'Sensores Inteligentes', 'name' => 'Sensor Inteligente de Puerta y Ventana', 'brand' => 'SafeHome', 'price' => 349.00, 'keyword' => 'sensor inteligente'],
            ['category' => 'Redes y Conectividad', 'family' => 'Routers', 'name' => 'Router WiFi 6 Dual Band AX3000', 'brand' => 'NetWave', 'price' => 2499.00, 'keyword' => 'router wifi 6'],
            ['category' => 'Redes y Conectividad', 'family' => 'Routers', 'name' => 'Router Mesh HomePack 2 Nodos', 'brand' => 'MeshLink', 'price' => 4299.00, 'keyword' => 'mesh wifi'],
            ['category' => 'Redes y Conectividad', 'family' => 'Switches', 'name' => 'Switch Gigabit 8 Puertos Metálico', 'brand' => 'NetCore', 'price' => 899.00, 'keyword' => 'switch gigabit'],
            ['category' => 'Redes y Conectividad', 'family' => 'Extensores WiFi', 'name' => 'Extensor WiFi AC1200 Doble Banda', 'brand' => 'RangePlus', 'price' => 799.00, 'keyword' => 'extensor wifi'],
            ['category' => 'Redes y Conectividad', 'family' => 'Cables de Red', 'name' => 'Cable de Red Cat 6 10m Azul', 'brand' => 'LinkPro', 'price' => 229.00, 'keyword' => 'cable red'],
            ['category' => 'Componentes de PC', 'family' => 'Procesadores', 'name' => 'Procesador Ryzen 5 7600 6 Núcleos', 'brand' => 'AMD', 'price' => 4599.00, 'keyword' => 'procesador'],
            ['category' => 'Componentes de PC', 'family' => 'Procesadores', 'name' => 'Procesador Core i5 14400F', 'brand' => 'Intel', 'price' => 4299.00, 'keyword' => 'cpu intel'],
            ['category' => 'Componentes de PC', 'family' => 'Tarjetas Madre', 'name' => 'Tarjeta Madre B650M WiFi DDR5', 'brand' => 'BoardMax', 'price' => 3299.00, 'keyword' => 'tarjeta madre'],
            ['category' => 'Componentes de PC', 'family' => 'Memorias RAM', 'name' => 'Memoria RAM DDR5 32GB 6000MHz', 'brand' => 'HyperMem', 'price' => 2599.00, 'keyword' => 'memoria ram'],
            ['category' => 'Componentes de PC', 'family' => 'Almacenamiento', 'name' => 'SSD NVMe 1TB Gen4 7000MB/s', 'brand' => 'SpeedDisk', 'price' => 1899.00, 'keyword' => 'ssd nvme'],
            ['category' => 'Wearables', 'family' => 'Smartwatches', 'name' => 'Smartwatch FitTime Pro AMOLED', 'brand' => 'FitTime', 'price' => 2499.00, 'keyword' => 'smartwatch'],
            ['category' => 'Wearables', 'family' => 'Smartwatches', 'name' => 'Smartwatch Active GPS 45mm', 'brand' => 'ActiveGo', 'price' => 3299.00, 'keyword' => 'reloj inteligente'],
            ['category' => 'Wearables', 'family' => 'Bandas Deportivas', 'name' => 'Banda Deportiva PulseBand 2', 'brand' => 'PulseBand', 'price' => 899.00, 'keyword' => 'banda deportiva'],
            ['category' => 'Wearables', 'family' => 'Lentes Inteligentes', 'name' => 'Lentes Inteligentes ViewAR Lite', 'brand' => 'ViewAR', 'price' => 5999.00, 'keyword' => 'lentes inteligentes'],
            ['category' => 'Wearables', 'family' => 'Rastreadores GPS', 'name' => 'Rastreador GPS TagTrack Compacto', 'brand' => 'TagTrack', 'price' => 699.00, 'keyword' => 'rastreador gps'],
        ];

        Product::query()->delete();

        $categories = Category::with('families')->get()->keyBy('name');

        foreach ($products as $index => $productData) {
            $category = $categories->get($productData['category']);
            if (!$category) {
                continue;
            }

            $family = $category->families->firstWhere('name', $productData['family']);
            $counter = $index + 1;

            Product::create([
                'category_id' => $category->id,
                'family_id' => $family?->id,
                'microsip_id' => 'MS-' . str_pad((string) $counter, 6, '0', STR_PAD_LEFT),
                'name' => $productData['name'],
                'slug' => Str::slug($productData['name'] . '-' . $counter),
                'description' => $productData['name'] . ' para pruebas de catálogo tecnológico, filtros por categoría, familias y flujo de carrito.',
                'image_path' => $imagePath,
                'default_price' => $productData['price'],
                'sku' => 'TECH-' . str_pad((string) $counter, 4, '0', STR_PAD_LEFT),
                'short_description' => 'Producto tecnológico de prueba para ' . $category->name . '.',
                'is_active' => true,
                'brand' => $productData['brand'],
                'keyword' => $productData['keyword'],
                'processed' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
