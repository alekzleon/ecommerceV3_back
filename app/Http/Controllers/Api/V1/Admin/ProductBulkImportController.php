<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ImportProductsRequest;
use App\Services\ProductBulkImportService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductBulkImportController extends Controller
{
    public function __construct(protected ProductBulkImportService $productBulkImportService)
    {
    }

    public function layout(): BinaryFileResponse
    {
        $path = $this->productBulkImportService->createLayoutWorkbookPath();

        return response()->download(
            $path,
            'layout-carga-productos.xlsx',
            ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']
        )->deleteFileAfterSend(true);
    }

    public function preview(ImportProductsRequest $request): JsonResponse
    {
        $result = $this->productBulkImportService->preview(
            file: $request->file('file'),
            mode: $request->input('mode', 'create_or_update')
        );

        return response()->json([
            'ok' => true,
            'message' => 'Archivo validado correctamente.',
            'data' => $result,
        ]);
    }

    public function import(ImportProductsRequest $request): JsonResponse
    {
        $result = $this->productBulkImportService->import(
            file: $request->file('file'),
            mode: $request->input('mode', 'create_or_update'),
            importImages: (bool) $request->boolean('import_images', false)
        );

        return response()->json([
            'ok' => true,
            'message' => 'Carga masiva de productos procesada correctamente.',
            'data' => $result,
        ]);
    }
}
