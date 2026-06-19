<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ContactFaq\ContactFaqResource;
use App\Models\ContactFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContactFaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = (int) $request->integer('limit', 0);

        $query = ContactFaq::query()
            ->active()
            ->ordered();

        $faqs = $limit > 0
            ? $query->limit(min($limit, 100))->get()
            : $query->get();

        return response()->json([
            'ok' => true,
            'data' => ContactFaqResource::collection($faqs),
        ]);
    }
}
