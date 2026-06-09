<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class CartExcelService
{
    public function __construct(
        protected CartService $cartService,
        protected ProductPriceService $productPriceService
    ) {
    }

    public function createLayoutWorkbookPath(?User $user = null): string
    {
        $products = Product::query()
            ->with(['category:id,name', 'family:id,name'])
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $this->productPriceService->decorateProducts($products, $user);

        $inventoryRows = $products
            ->map(fn (Product $product) => [
                (string) $product->id,
                (string) $product->sku,
                (string) $product->name,
                (string) ($product->brand ?? ''),
                (string) ($product->category?->name ?? ''),
                (string) ($product->family?->name ?? ''),
                number_format((float) ($product->getAttribute('current_price') ?? $product->default_price), 2, '.', ''),
                $product->is_active ? '1' : '0',
            ])
            ->values()
            ->all();

        $sheets = [
            [
                'name' => 'CargaCarrito',
                'rows' => [
                    ['sku', 'quantity'],
                    ['SKU001', '2'],
                ],
            ],
            [
                'name' => 'Inventario',
                'rows' => array_merge([
                    ['product_id', 'sku', 'name', 'brand', 'category', 'family', 'default_price', 'is_active'],
                ], $inventoryRows),
            ],
        ];

        return $this->buildWorkbook($sheets);
    }

    public function importIntoCart(User $user, UploadedFile $file): array
    {
        $rows = $this->readFirstSheetRows($file->getRealPath());

        if (count($rows) < 2) {
            throw new RuntimeException('El archivo no contiene filas para procesar.');
        }

        $header = collect($rows[0])
            ->map(fn ($value) => mb_strtolower(trim((string) $value)))
            ->values();

        $skuIndex = $header->search('sku');
        $quantityIndex = $header->search('quantity');

        if ($skuIndex === false || $quantityIndex === false) {
            throw new RuntimeException('La hoja 1 debe incluir las columnas sku y quantity.');
        }

        $normalizedRows = [];
        $errors = [];

        foreach (array_slice($rows, 1) as $rowNumber => $row) {
            $excelRow = $rowNumber + 2;
            $sku = trim((string) ($row[$skuIndex] ?? ''));
            $quantityRaw = trim((string) ($row[$quantityIndex] ?? ''));

            if ($sku === '' && $quantityRaw === '') {
                continue;
            }

            if ($sku === '') {
                $errors[] = [
                    'row' => $excelRow,
                    'sku' => null,
                    'message' => 'La fila no tiene SKU.',
                ];
                continue;
            }

            if ($quantityRaw === '' || !is_numeric($quantityRaw)) {
                $errors[] = [
                    'row' => $excelRow,
                    'sku' => $sku,
                    'message' => 'La cantidad no es válida.',
                ];
                continue;
            }

            $quantity = round((float) $quantityRaw, 2);

            if ($quantity <= 0) {
                $errors[] = [
                    'row' => $excelRow,
                    'sku' => $sku,
                    'message' => 'La cantidad debe ser mayor a cero.',
                ];
                continue;
            }

            $normalizedRows[] = [
                'row' => $excelRow,
                'sku' => $sku,
                'quantity' => $quantity,
            ];
        }

        if (empty($normalizedRows) && !empty($errors)) {
            return [
                'cart' => $this->cartService->getOrCreateActiveCart($user),
                'summary' => [
                    'processed_rows' => 0,
                    'imported_rows' => 0,
                    'skipped_rows' => count($errors),
                    'imported_items' => [],
                    'errors' => $errors,
                ],
            ];
        }

        $groupedBySku = collect($normalizedRows)
            ->groupBy(fn (array $row) => mb_strtoupper($row['sku']))
            ->map(function (Collection $rows, string $sku) {
                return [
                    'sku' => $sku,
                    'quantity' => round((float) $rows->sum('quantity'), 2),
                    'rows' => $rows->pluck('row')->values()->all(),
                ];
            })
            ->values();

        $products = Product::query()
            ->with(['category', 'family'])
            ->whereIn(DB::raw('UPPER(sku)'), $groupedBySku->pluck('sku')->all())
            ->get()
            ->keyBy(fn (Product $product) => mb_strtoupper((string) $product->sku));

        $validRows = [];

        foreach ($groupedBySku as $row) {
            /** @var Product|null $product */
            $product = $products->get($row['sku']);

            if (! $product) {
                $errors[] = [
                    'row' => implode(', ', $row['rows']),
                    'sku' => $row['sku'],
                    'message' => 'El SKU no existe.',
                ];
                continue;
            }

            if (! (bool) $product->is_active) {
                $errors[] = [
                    'row' => implode(', ', $row['rows']),
                    'sku' => $row['sku'],
                    'message' => 'El producto está inactivo.',
                ];
                continue;
            }

            if ((float) $this->productPriceService->priceForProduct($product, $user)['price'] <= 0) {
                $errors[] = [
                    'row' => implode(', ', $row['rows']),
                    'sku' => $row['sku'],
                    'message' => 'El producto no tiene precio válido.',
                ];
                continue;
            }

            $validRows[] = [
                'product' => $product,
                'quantity' => $row['quantity'],
                'rows' => $row['rows'],
            ];
        }

        $cart = $this->cartService->bulkAddItems($user, $validRows);

        return [
            'cart' => $cart,
            'summary' => [
                'processed_rows' => count($normalizedRows),
                'imported_rows' => count($validRows),
                'skipped_rows' => count($errors),
                'imported_items' => collect($validRows)
                    ->map(fn (array $row) => [
                        'product_id' => $row['product']->id,
                        'sku' => $row['product']->sku,
                        'name' => $row['product']->name,
                        'quantity' => $row['quantity'],
                        'rows' => $row['rows'],
                    ])
                    ->values()
                    ->all(),
                'errors' => array_values($errors),
            ],
        ];
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
                $columnLetters = preg_replace('/\d+/', '', $reference);
                $columnIndex = $this->columnLettersToIndex($columnLetters);
                $value = $this->cellValue($cell, $sharedStrings);
                $cells[$columnIndex] = $value;
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
        $tempPath = tempnam(sys_get_temp_dir(), 'cart-layout-');

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
                    if (!array_key_exists($value, $stringIndexMap)) {
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
            $sheetXml .= '<sheet name="' . $this->xmlEscape($sheet['name']) . '" sheetId="' . ($index + 1) . '" r:id="rId' . ($index + 1) . '"/>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets>' . $sheetXml . '</sheets>'
            . '</workbook>';
    }

    protected function workbookRelsXml(int $sheetCount): string
    {
        $relationships = '';

        for ($i = 1; $i <= $sheetCount; $i++) {
            $relationships .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
        }

        $relationships .= '<Relationship Id="rId' . ($sheetCount + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';
        $relationships .= '<Relationship Id="rId' . ($sheetCount + 2) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>';

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . $relationships
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
            . '<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>'
            . '</styleSheet>';
    }

    protected function sharedStringsXml(array $sharedStrings): string
    {
        $items = collect($sharedStrings)
            ->map(fn (string $value) => '<si><t>' . $this->xmlEscape($value) . '</t></si>')
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">'
            . $items
            . '</sst>';
    }

    protected function worksheetXml(array $rows, array $stringIndexMap): string
    {
        $sheetRows = '';

        foreach ($rows as $rowIndex => $row) {
            $cells = '';

            foreach (array_values($row) as $columnIndex => $value) {
                $value = (string) $value;

                if ($value === '') {
                    continue;
                }

                $cellRef = $this->indexToColumnLetters($columnIndex) . ($rowIndex + 1);
                $sharedIndex = $stringIndexMap[$value] ?? 0;
                $cells .= '<c r="' . $cellRef . '" t="s"><v>' . $sharedIndex . '</v></c>';
            }

            $sheetRows .= '<row r="' . ($rowIndex + 1) . '">' . $cells . '</row>';
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<sheetData>' . $sheetRows . '</sheetData>'
            . '</worksheet>';
    }

    protected function indexToColumnLetters(int $index): string
    {
        $letters = '';
        $index++;

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod) . $letters;
            $index = intdiv($index - $mod, 26);
        }

        return $letters;
    }

    protected function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
