<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Banner\BannerResource;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 0);

        $query = Banner::query()
            ->active()
            ->currentWindow()
            ->ordered();

        if ($request->filled('media_type')) {
            $query->where('media_type', $request->string('media_type')->toString());
        }

        $banners = $limit > 0
            ? $query->limit(min($limit, 100))->get()
            : $query->get();

        return response()->json([
            'ok' => true,
            'data' => BannerResource::collection($banners),
        ]);
    }
}
