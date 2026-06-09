<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGiftItemRequest;
use App\Http\Requests\Admin\UpdateGiftItemRequest;
use App\Http\Resources\GiftItem\GiftItemResource;
use App\Models\GiftItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GiftItemController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        $query = GiftItem::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%");
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
                'data' => GiftItemResource::collection($query->get()),
            ]);
        }

        $giftItems = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'data' => GiftItemResource::collection($giftItems->getCollection()),
            'meta' => [
                'current_page' => $giftItems->currentPage(),
                'last_page' => $giftItems->lastPage(),
                'per_page' => $giftItems->perPage(),
                'total' => $giftItems->total(),
                'from' => $giftItems->firstItem(),
                'to' => $giftItems->lastItem(),
            ],
        ]);
    }

    public function store(StoreGiftItemRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            $data['image_disk'] = 'public';
            $data['image_path'] = $request->file('image')->store('gift-items', 'public');
        }

        unset($data['image']);

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ((int) GiftItem::query()->max('sort_order')) + 1;
        }

        $giftItem = GiftItem::create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Artículo de regalo creado correctamente.',
            'data' => new GiftItemResource($giftItem),
        ], 201);
    }

    public function show(GiftItem $giftItem): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => new GiftItemResource($giftItem),
        ]);
    }

    public function update(UpdateGiftItemRequest $request, GiftItem $giftItem): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('image')) {
            if ($giftItem->image_path && Storage::disk($giftItem->image_disk ?: 'public')->exists($giftItem->image_path)) {
                Storage::disk($giftItem->image_disk ?: 'public')->delete($giftItem->image_path);
            }

            $data['image_disk'] = 'public';
            $data['image_path'] = $request->file('image')->store('gift-items', 'public');
        }

        unset($data['image']);

        $giftItem->update($data);

        return response()->json([
            'ok' => true,
            'message' => 'Artículo de regalo actualizado correctamente.',
            'data' => new GiftItemResource($giftItem->fresh()),
        ]);
    }

    public function destroy(GiftItem $giftItem): JsonResponse
    {
        if ($giftItem->image_path && Storage::disk($giftItem->image_disk ?: 'public')->exists($giftItem->image_path)) {
            Storage::disk($giftItem->image_disk ?: 'public')->delete($giftItem->image_path);
        }

        $giftItem->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Artículo de regalo eliminado correctamente.',
        ]);
    }

    public function toggle(GiftItem $giftItem): JsonResponse
    {
        $giftItem->update([
            'is_active' => ! $giftItem->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado del artículo de regalo actualizado correctamente.',
            'data' => new GiftItemResource($giftItem->fresh()),
        ]);
    }
}
