<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MonthlyPromotion\MonthlyPromotionResource;
use App\Models\MonthlyPromotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MonthlyPromotionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 0);

        $query = MonthlyPromotion::query()
            ->active()
            ->currentWindow()
            ->ordered();

            $promotions = $limit > 0
            ? $query->limit(min($limit, 100))->get()
            : $query->get();
            
        return response()->json([
            'ok' => true,
            'data' => MonthlyPromotionResource::collection($promotions),
        ]);
    }
}
