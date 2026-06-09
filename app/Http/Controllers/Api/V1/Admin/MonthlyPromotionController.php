<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderMonthlyPromotionsRequest;
use App\Http\Requests\Admin\StoreMonthlyPromotionRequest;
use App\Http\Requests\Admin\UpdateMonthlyPromotionRequest;
use App\Http\Resources\MonthlyPromotion\MonthlyPromotionResource;
use App\Models\MonthlyPromotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MonthlyPromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        $query = MonthlyPromotion::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhere('button_text', 'like', "%{$search}%");
                });
            })
            ->when($request->has('is_active') && $request->input('is_active') !== '', function ($query) use ($request) {
                $isActive = filter_var($request->input('is_active'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

                if ($isActive !== null) {
                    $query->where('is_active', $isActive);
                }
            })
            ->ordered();

        if (filter_var($request->input('without_pagination', false), FILTER_VALIDATE_BOOLEAN)) {
            return response()->json([
                'ok' => true,
                'data' => MonthlyPromotionResource::collection($query->get()),
            ]);
        }

        $promotions = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'data' => MonthlyPromotionResource::collection($promotions->getCollection()),
            'meta' => [
                'current_page' => $promotions->currentPage(),
                'last_page' => $promotions->lastPage(),
                'per_page' => $promotions->perPage(),
                'total' => $promotions->total(),
                'from' => $promotions->firstItem(),
                'to' => $promotions->lastItem(),
            ],
        ]);
    }

    public function store(StoreMonthlyPromotionRequest $request): JsonResponse
    {
        $data = $request->validated();
        $file = $request->file('image');

        $data['image_disk'] = 'public';
        $data['image_path'] = $file->store('monthly-promotions', 'public');

        unset($data['image']);

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ((int) MonthlyPromotion::query()->max('sort_order')) + 1;
        }

        $promotion = MonthlyPromotion::create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Promoción del mes creada correctamente.',
            'data' => new MonthlyPromotionResource($promotion),
        ], 201);
    }

    public function show(MonthlyPromotion $monthlyPromotion): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => new MonthlyPromotionResource($monthlyPromotion),
        ]);
    }

    public function update(UpdateMonthlyPromotionRequest $request, MonthlyPromotion $monthlyPromotion): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if (
                $monthlyPromotion->image_path
                && Storage::disk($monthlyPromotion->image_disk ?: 'public')->exists($monthlyPromotion->image_path)
            ) {
                Storage::disk($monthlyPromotion->image_disk ?: 'public')->delete($monthlyPromotion->image_path);
            }

            $data['image_disk'] = 'public';
            $data['image_path'] = $request->file('image')->store('monthly-promotions', 'public');
        }

        unset($data['image']);

        $monthlyPromotion->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Promoción del mes actualizada correctamente.',
            'data' => new MonthlyPromotionResource($monthlyPromotion->fresh()),
        ]);
    }

    public function destroy(MonthlyPromotion $monthlyPromotion): JsonResponse
    {
        if (
            $monthlyPromotion->image_path
            && Storage::disk($monthlyPromotion->image_disk ?: 'public')->exists($monthlyPromotion->image_path)
        ) {
            Storage::disk($monthlyPromotion->image_disk ?: 'public')->delete($monthlyPromotion->image_path);
        }

        $monthlyPromotion->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Promoción del mes eliminada correctamente.',
        ]);
    }

    public function toggle(MonthlyPromotion $monthlyPromotion): JsonResponse
    {
        $monthlyPromotion->update([
            'is_active' => ! $monthlyPromotion->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado de la promoción del mes actualizado correctamente.',
            'data' => new MonthlyPromotionResource($monthlyPromotion->fresh()),
        ]);
    }

    public function reorder(ReorderMonthlyPromotionsRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('monthly_promotions') as $promotionData) {
                MonthlyPromotion::query()
                    ->whereKey($promotionData['id'])
                    ->update(['sort_order' => $promotionData['sort_order']]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Orden de promociones del mes actualizado correctamente.',
            'data' => MonthlyPromotionResource::collection(MonthlyPromotion::query()->ordered()->get()),
        ]);
    }
}
