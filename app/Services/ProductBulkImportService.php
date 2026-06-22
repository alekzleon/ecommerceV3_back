<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Family;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class ProductBulkImportService
{
    public const REQUIRED_COLUMNS = [
        'name',
        'default_price',
        'category_name',
    ];

    public const OPTIONAL_COLUMNS = [
        'sku',
        'brand',
        'short_description',
        'description',
        'stock',
        'keyword',
        'category_id',
        'family_id',
        'family_name',
        'image_path',
        'image_url',
        'is_active',
    ];

    public function createLayoutWorkbookPath(): string
    {
        return $this->buildWorkbook([
            [
                'name' => 'Productos',
                'rows' => [
                    [
                        'sku',
                        'name',
                        'brand',
                        'short_description',
                        'description',
                        'default_price',
                        'stock',
                        'keyword',
                        'category_name',
                        'family_name',
                        'image_url',
                        'image_path',
                        'is_active',
                    ],
                    [
                        'SKU-001',
                        'Playera CloudiShop',
                        'CloudiShop',
                        'Playera basica para venta online',
                        'Descripcion larga del producto.',
                        '299.00',
                        '20',
                        'regalo moda playera',
                        'Ropa',
                        'Playeras',
                        'https://example.com/imagen.jpg',
                        '',
                        '1',
                    ],
                ],
            ],
            [
                'name' => 'Glosario',
                'rows' => [
                    ['CloudiShop'],
                    ['Carga masiva de productos'],
                    ['Usa la hoja Productos para capturar tus articulos. No cambies el nombre de las columnas.'],
                    [''],
                    ['Columna', 'Nombre para el usuario', 'Obligatoria', 'Descripcion', 'Ejemplo'],
                    ['sku', 'SKU o clave', 'No', 'Clave interna para identificar el producto. Si ya existe, se puede actualizar en modo create_or_update.', 'SKU-001'],
                    ['name', 'Nombre del producto', 'Si', 'Nombre visible en la tienda.', 'Playera CloudiShop'],
                    ['brand', 'Marca', 'No', 'Marca comercial del producto. Es texto libre; no necesitas darla de alta antes.', 'CloudiShop'],
                    ['short_description', 'Descripcion corta', 'No', 'Texto breve para cards, listados o resumen del producto.', 'Playera basica para venta online'],
                    ['description', 'Descripcion larga', 'No', 'Detalle completo del producto, materiales, beneficios o instrucciones.', 'Descripcion larga del producto.'],
                    ['default_price', 'Precio', 'Si', 'Precio de venta en MXN. Usa numeros sin simbolo de pesos.', '299.00'],
                    ['stock', 'Stock', 'No', 'Existencia disponible. Si lo dejas vacio, queda sin control de stock.', '20'],
                    ['keyword', 'Palabras clave', 'No', 'Palabras para ayudar al buscador inteligente. Separalas por espacios o comas.', 'regalo moda playera'],
                    ['category_name', 'Categoria', 'Si', 'Agrupacion principal del producto. Si no existe, CloudiShop la crea automaticamente.', 'Ropa'],
                    ['family_name', 'Familia o subcategoria', 'No', 'Subgrupo dentro de la categoria. Si no existe, CloudiShop la crea dentro de esa categoria.', 'Playeras'],
                    ['image_url', 'URL de imagen', 'No', 'Liga publica de la imagen. Solo se descarga si import_images=true.', 'https://example.com/imagen.jpg'],
                    ['image_path', 'Ruta de imagen existente', 'No', 'Ruta ya existente en storage/public. Usala si la imagen ya fue cargada al servidor.', 'products/mi-imagen.jpg'],
                    ['is_active', 'Producto activo', 'No', '1 para publicar/activar, 0 para dejar inactivo.', '1'],
                    [''],
                    ['Regla importante'],
                    ['Categoria y familia se escriben como texto. Ejemplo: Categoria Ropa, Familia Playeras. No necesitas bajar todo el catalogo existente.'],
                    ['Si repites exactamente el mismo nombre de categoria/familia, se reutiliza. Si escribes uno nuevo, se crea.'],
                    ['Para marcas usa la columna brand como texto libre. Ejemplo: Nike, Apple, CloudiShop, Mi Marca.'],
                    [''],
                    ['Flujo recomendado'],
                    ['1. Descarga esta plantilla.'],
                    ['2. Llena maximo unas filas de prueba.'],
                    ['3. Sube el archivo a previsualizar.'],
                    ['4. Corrige errores.'],
                    ['5. Confirma la importacion.'],
                ],
            ],
            [
                'name' => 'Ejemplos',
                'rows' => [
                    ['sku', 'name', 'brand', 'default_price', 'stock', 'category_name', 'family_name', 'keyword'],
                    ['SKU-001', 'Playera CloudiShop', 'CloudiShop', '299.00', '20', 'Ropa', 'Playeras', 'regalo moda playera'],
                    ['SKU-002', 'Taza personalizada', 'Mi Marca', '149.00', '50', 'Regalos', 'Tazas', 'regalo mama taza personalizada'],
                    ['SKU-003', 'Kit de cuidado personal', 'Beauty Lab', '399.00', '15', 'Belleza', 'Kits', 'regalo mujer cuidado belleza'],
                    [''],
                    ['Como armar categorias y familias'],
                    ['Categoria es el grupo principal: Ropa, Belleza, Accesorios, Electronica, Regalos.'],
                    ['Familia es el subgrupo: Playeras, Skincare, Bolsas, Audifonos, Tazas.'],
                    ['Ejemplo correcto: Categoria Belleza + Familia Skincare.'],
                    ['Ejemplo correcto: Categoria Electronica + Familia Audifonos.'],
                    ['Evita mezclar todo en categoria. No uses categoria Playeras manga corta mujer; mejor Categoria Ropa + Familia Playeras.'],
                ],
            ],
        ]);
    }

    public function preview(UploadedFile $file, string $mode = 'create_or_update'): array
    {
        return $this->process($file, $mode, false, false);
    }

    public function import(UploadedFile $file, string $mode = 'create_or_update', bool $importImages = false): array
    {
        return $this->process($file, $mode, true, $importImages);
    }

    protected function process(UploadedFile $file, string $mode, bool $commit, bool $importImages): array
    {
        $rows = $this->readFirstSheetRows($file->getRealPath());

        if (count($rows) < 2) {
            throw new RuntimeException('El archivo no contiene productos para procesar.');
        }

        $header = collect($rows[0])
            ->map(fn ($value) => $this->normalizeHeader((string) $value))
            ->values();

        $missingColumns = collect(self::REQUIRED_COLUMNS)
            ->filter(fn ($column) => $header->search($column) === false)
            ->values()
            ->all();

        if (! empty($missingColumns)) {
            throw new RuntimeException('Faltan columnas obligatorias: ' . implode(', ', $missingColumns) . '.');
        }

        $summary = [
            'processed_rows' => 0,
            'valid_rows' => 0,
            'created_rows' => 0,
            'updated_rows' => 0,
            'skipped_rows' => 0,
            'mode' => $mode,
            'commit' => $commit,
            'import_images' => $importImages,
        ];
        $items = [];
        $errors = [];

        foreach (array_slice($rows, 1) as $rowIndex => $row) {
            $excelRow = $rowIndex + 2;
            $data = $this->rowToData($header, $row);

            if ($this->isEmptyProductRow($data)) {
                continue;
            }

            $summary['processed_rows']++;
            $validation = $this->validateRow($data, $excelRow);

            if (! empty($validation['errors'])) {
                $summary['skipped_rows']++;
                $errors = array_merge($errors, $validation['errors']);
                $items[] = $this->itemPayload($excelRow, $data, 'invalid', $validation['errors']);
                continue;
            }

            $existingProduct = $this->findExistingProduct($data);
            $action = $existingProduct ? 'update' : 'create';

            if ($existingProduct && $mode === 'create_only') {
                $summary['skipped_rows']++;
                $errors[] = [
                    'row' => $excelRow,
                    'sku' => $data['sku'] ?? null,
                    'message' => 'El producto ya existe y el modo create_only no permite actualizar.',
                ];
                $items[] = $this->itemPayload($excelRow, $data, 'skipped');
                continue;
            }

            $summary['valid_rows']++;

            if (! $commit) {
                $items[] = $this->itemPayload($excelRow, $data, $action, [], $existingProduct);
                continue;
            }

            $result = $this->saveProduct($data, $existingProduct, $importImages);
            $summary[$result['action'] === 'created' ? 'created_rows' : 'updated_rows']++;
            $items[] = $this->itemPayload($excelRow, $data, $result['action'], $result['warnings'], $result['product']);
        }

        return [
            'summary' => $summary,
            'items' => $items,
            'errors' => $errors,
        ];
    }

    protected function rowToData($header, array $row): array
    {
        $data = [];

        foreach ($header as $index => $column) {
            if ($column === '') {
                continue;
            }

            $data[$column] = trim((string) ($row[$index] ?? ''));
        }

        return $data;
    }

    protected function validateRow(array $data, int $excelRow): array
    {
        $errors = [];

        foreach (self::REQUIRED_COLUMNS as $column) {
            if (blank($data[$column] ?? null)) {
                $errors[] = [
                    'row' => $excelRow,
                    'field' => $column,
                    'message' => "La columna {$column} es obligatoria.",
                ];
            }
        }

        if (filled($data['default_price'] ?? null) && ! is_numeric($data['default_price'])) {
            $errors[] = [
                'row' => $excelRow,
                'field' => 'default_price',
                'message' => 'El precio debe ser numérico.',
            ];
        }

        if (filled($data['stock'] ?? null) && ! is_numeric($data['stock'])) {
            $errors[] = [
                'row' => $excelRow,
                'field' => 'stock',
                'message' => 'El stock debe ser numérico.',
            ];
        }

        if (filled($data['image_url'] ?? null) && ! filter_var($data['image_url'], FILTER_VALIDATE_URL)) {
            $errors[] = [
                'row' => $excelRow,
                'field' => 'image_url',
                'message' => 'La URL de imagen no es válida.',
            ];
        }

        return ['errors' => $errors];
    }

    protected function saveProduct(array $data, ?Product $product, bool $importImages): array
    {
        $warnings = [];
        $category = $this->resolveCategory($data);
        $family = $this->resolveFamily($data, $category);
        $payload = [
            'category_id' => $category->id,
            'family_id' => $family?->id,
            'name' => $data['name'],
            'sku' => ($data['sku'] ?? '') ?: null,
            'brand' => ($data['brand'] ?? '') ?: null,
            'short_description' => ($data['short_description'] ?? '') ?: null,
            'description' => ($data['description'] ?? '') ?: null,
            'default_price' => round((float) $data['default_price'], 2),
            'stock' => filled($data['stock'] ?? null) ? round((float) $data['stock'], 2) : null,
            'keyword' => ($data['keyword'] ?? '') ?: null,
            'is_active' => $this->booleanValue($data['is_active'] ?? '1'),
        ];

        if (filled($data['image_path'] ?? null)) {
            $payload['image_path'] = ltrim((string) $data['image_path'], '/');
        } elseif ($importImages && filled($data['image_url'] ?? null)) {
            $downloadedPath = $this->downloadImage((string) $data['image_url']);

            if ($downloadedPath) {
                $payload['image_path'] = $downloadedPath;
            } else {
                $warnings[] = 'No fue posible descargar la imagen remota.';
            }
        }

        if ($product) {
            $product->update($payload);
            $action = 'updated';
        } else {
            $product = Product::create($payload);
            $action = 'created';
        }

        $product->load(['category', 'family']);

        return [
            'action' => $action,
            'product' => $product,
            'warnings' => $warnings,
        ];
    }

    protected function resolveCategory(array $data): Category
    {
        if (filled($data['category_id'] ?? null)) {
            $category = Category::query()->find((int) $data['category_id']);

            if ($category) {
                return $category;
            }
        }

        return Category::query()->firstOrCreate(
            ['slug' => Str::slug((string) $data['category_name'])],
            ['name' => (string) $data['category_name'], 'is_active' => true]
        );
    }

    protected function resolveFamily(array $data, Category $category): ?Family
    {
        if (filled($data['family_id'] ?? null)) {
            $family = Family::query()
                ->where('category_id', $category->id)
                ->find((int) $data['family_id']);

            if ($family) {
                return $family;
            }
        }

        if (blank($data['family_name'] ?? null)) {
            return null;
        }

        return Family::query()->firstOrCreate(
            [
                'category_id' => $category->id,
                'slug' => Str::slug((string) $data['family_name']),
            ],
            [
                'name' => (string) $data['family_name'],
                'grupo_linea_id' => $category->grupo_linea_id,
                'is_active' => true,
            ]
        );
    }

    protected function findExistingProduct(array $data): ?Product
    {
        if (filled($data['sku'] ?? null)) {
            $product = Product::query()
                ->whereRaw('UPPER(sku) = ?', [mb_strtoupper((string) $data['sku'])])
                ->first();

            if ($product) {
                return $product;
            }
        }

        if (filled($data['name'] ?? null)) {
            return Product::query()
                ->where('slug', Str::slug((string) $data['name']))
                ->first();
        }

        return null;
    }

    protected function itemPayload(
        int $row,
        array $data,
        string $status,
        array $messages = [],
        ?Product $product = null
    ): array {
        return [
            'row' => $row,
            'status' => $status,
            'product_id' => $product?->id,
            'sku' => $data['sku'] ?? null,
            'name' => $data['name'] ?? null,
            'default_price' => filled($data['default_price'] ?? null) ? (float) $data['default_price'] : null,
            'stock' => filled($data['stock'] ?? null) ? (float) $data['stock'] : null,
            'category_name' => $data['category_name'] ?? null,
            'family_name' => $data['family_name'] ?? null,
            'messages' => $messages,
        ];
    }

    protected function downloadImage(string $url): ?string
    {
        try {
            $response = Http::timeout(8)->get($url);

            if (! $response->successful()) {
                return null;
            }

            $contentType = strtolower((string) $response->header('Content-Type'));
            $extension = match (true) {
                str_contains($contentType, 'png') => 'png',
                str_contains($contentType, 'webp') => 'webp',
                str_contains($contentType, 'jpeg'), str_contains($contentType, 'jpg') => 'jpg',
                default => pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: 'jpg',
            };

            if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                return null;
            }

            $path = 'products/imports/' . Str::uuid() . '.' . $extension;
            Storage::disk('public')->put($path, $response->body());

            return $path;
        } catch (\Throwable) {
            return null;
        }
    }

    protected function isEmptyProductRow(array $data): bool
    {
        return collect($data)->filter(fn ($value) => filled($value))->isEmpty();
    }

    protected function booleanValue(mixed $value): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? in_array((string) $value, ['1', 'si', 'sí'], true);
    }

    protected function normalizeHeader(string $header): string
    {
        return Str::of($header)->lower()->ascii()->trim()->replace([' ', '-'], '_')->toString();
    }

    protected function readFirstSheetRows(string $path): array
    {
        $zip = new ZipArchive();

        if ($zip->open($path) !== true) {
            throw new RuntimeException('No fue posible abrir el archivo Excel.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetPath = $this->resolveFirstSheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('No fue posible leer la hoja principal del archivo Excel.');
        }

        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet instanceof SimpleXMLElement) {
            throw new RuntimeException('El archivo Excel no tiene un formato válido.');
        }

        $namespaces = $sheet->getNamespaces(true);
        $sheet->registerXPathNamespace('main', $namespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

        $rows = [];

        foreach ($sheet->xpath('//main:sheetData/main:row') ?: [] as $row) {
            $cells = [];
            $maxIndex = -1;

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnLettersToIndex(preg_replace('/\d+/', '', $reference));
                $cells[$columnIndex] = $this->cellValue($cell, $sharedStrings);
                $maxIndex = max($maxIndex, $columnIndex);
            }

            if ($maxIndex < 0) {
                continue;
            }

            $normalized = [];

            for ($i = 0; $i <= $maxIndex; $i++) {
                $normalized[] = $cells[$i] ?? '';
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    protected function readSharedStrings(ZipArchive $zip): array
    {
        $sharedStringsXml = $zip->getFromName('xl/sharedStrings.xml');

        if ($sharedStringsXml === false) {
            return [];
        }

        $xml = simplexml_load_string($sharedStringsXml);

        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($xml->si as $item) {
            if (isset($item->t)) {
                $strings[] = (string) $item->t;
                continue;
            }

            $text = '';
            foreach ($item->r as $run) {
                $text .= (string) $run->t;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    protected function resolveFirstSheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            throw new RuntimeException('No fue posible leer la estructura interna del archivo Excel.');
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if (! $workbook instanceof SimpleXMLElement || ! $rels instanceof SimpleXMLElement) {
            throw new RuntimeException('El archivo Excel no tiene una estructura válida.');
        }

        $workbookNamespaces = $workbook->getNamespaces(true);
        $relsNamespaces = $rels->getNamespaces(true);

        $workbook->registerXPathNamespace('main', $workbookNamespaces[''] ?? 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', $workbookNamespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rels->registerXPathNamespace('rel', $relsNamespaces[''] ?? 'http://schemas.openxmlformats.org/package/2006/relationships');

        $sheet = ($workbook->xpath('//main:sheets/main:sheet')[0] ?? null);

        if (! $sheet) {
            throw new RuntimeException('El archivo Excel no contiene hojas.');
        }

        $relationshipId = (string) $sheet->attributes($workbookNamespaces['r'] ?? 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;

        foreach ($rels->xpath('//rel:Relationship') ?: [] as $relationship) {
            if ((string) $relationship['Id'] === $relationshipId) {
                return 'xl/' . ltrim((string) $relationship['Target'], '/');
            }
        }

        throw new RuntimeException('No fue posible localizar la hoja principal del archivo Excel.');
    }

    protected function cellValue(SimpleXMLElement $cell, array $sharedStrings): string
    {
        $type = (string) $cell['t'];

        if ($type === 'inlineStr') {
            return trim((string) ($cell->is->t ?? ''));
        }

        $value = (string) ($cell->v ?? '');

        if ($type === 's') {
            return trim((string) ($sharedStrings[(int) $value] ?? ''));
        }

        return trim($value);
    }

    protected function columnLettersToIndex(string $letters): int
    {
        $letters = strtoupper($letters);
        $index = 0;

        for ($i = 0; $i < strlen($letters); $i++) {
            $index = ($index * 26) + (ord($letters[$i]) - 64);
        }

        return $index - 1;
    }

    protected function buildWorkbook(array $sheets): string
    {
        $tempPath = tempnam(sys_get_temp_dir(), 'product-layout-');

        if ($tempPath === false) {
            throw new RuntimeException('No fue posible crear el archivo temporal para el Excel.');
        }

        $xlsxPath = $tempPath . '.xlsx';
        @unlink($xlsxPath);
        rename($tempPath, $xlsxPath);

        $zip = new ZipArchive();

        if ($zip->open($xlsxPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('No fue posible generar el archivo Excel.');
        }

        $sharedStrings = [];
        $stringIndexMap = [];

        foreach ($sheets as $sheet) {
            foreach ($sheet['rows'] as $row) {
                foreach ($row as $value) {
                    $value = (string) $value;
                    if ($value === '') {
                        continue;
                    }
                    if (! array_key_exists($value, $stringIndexMap)) {
                        $stringIndexMap[$value] = count($sharedStrings);
                        $sharedStrings[] = $value;
                    }
                }
            }
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml(count($sheets)));
        $zip->addFromString('_rels/.rels', $this->rootRelsXml());
        $zip->addFromString('xl/workbook.xml', $this->workbookXml($sheets));
        $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelsXml(count($sheets)));
        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->addFromString('xl/sharedStrings.xml', $this->sharedStringsXml($sharedStrings));

        foreach ($sheets as $index => $sheet) {
            $zip->addFromString(
                'xl/worksheets/sheet' . ($index + 1) . '.xml',
                $this->worksheetXml($sheet['rows'], $stringIndexMap)
            );
        }

        $zip->close();

        return $xlsxPath;
    }

    protected function contentTypesXml(int $sheetCount): string
    {
        $sheetOverrides = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $sheetOverrides .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
            . $sheetOverrides
            . '</Types>';
    }

    protected function rootRelsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';
    }

    protected function workbookXml(array $sheets): string
    {
        $sheetXml = '';

        foreach ($sheets as $index => $sheet) {
            $sheetId = $index + 1;
            $sheetXml .= '<sheet name="' . $this->xmlEscape($sheet['name']) . '" sheetId="' . $sheetId . '" r:id="rId' . $sheetId . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetXml . '</sheets>'
            . '</workbook>';
    }

    protected function workbookRelsXml(int $sheetCount): string
    {
        $rels = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $rels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }

        $rels .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $rels .= '<Relationship Id="rId' . ($sheetCount + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $rels
            . '</Relationships>';
    }

    protected function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>'
            . '<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellXfs>'
            . '</styleSheet>';
    }

    protected function sharedStringsXml(array $strings): string
    {
        $items = collect($strings)
            ->map(fn ($value) => '<si><t>' . $this->xmlEscape((string) $value) . '</t></si>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($strings) . '" uniqueCount="' . count($strings) . '">'
            . $items
            . '</sst>';
    }

    protected function worksheetXml(array $rows, array $stringIndexMap): string
    {
        $rowXml = '';

        foreach ($rows as $rowIndex => $row) {
            $excelRow = $rowIndex + 1;
            $cells = '';

            foreach ($row as $columnIndex => $value) {
                $value = (string) $value;

                if ($value === '') {
                    continue;
                }

                $cellRef = $this->columnIndexToLetters($columnIndex) . $excelRow;
                $sharedStringIndex = $stringIndexMap[$value];
                $cells .= '<c r="' . $cellRef . '" t="s"><v>' . $sharedStringIndex . '</v></c>';
            }

            $rowXml .= '<row r="' . $excelRow . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $rowXml . '</sheetData>'
            . '</worksheet>';
    }

    protected function columnIndexToLetters(int $index): string
    {
        $letters = '';
        $index++;

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = (int) floor(($index - $mod) / 26);
        }

        return $letters;
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_COMPAT, 'UTF-8');
    }
}
