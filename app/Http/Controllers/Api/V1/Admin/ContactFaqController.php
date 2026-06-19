<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderContactFaqsRequest;
use App\Http\Requests\Admin\StoreContactFaqRequest;
use App\Http\Requests\Admin\UpdateContactFaqRequest;
use App\Http\Resources\ContactFaq\ContactFaqResource;
use App\Models\ContactFaq;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactFaqController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        $query = ContactFaq::query()
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->search);

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('question', 'like', "%{$search}%")
                        ->orWhere('answer', 'like', "%{$search}%");
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
                'data' => ContactFaqResource::collection($query->get()),
            ]);
        }

        $faqs = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'ok' => true,
            'data' => ContactFaqResource::collection($faqs->getCollection()),
            'meta' => [
                'current_page' => $faqs->currentPage(),
                'last_page' => $faqs->lastPage(),
                'per_page' => $faqs->perPage(),
                'total' => $faqs->total(),
                'from' => $faqs->firstItem(),
                'to' => $faqs->lastItem(),
            ],
        ]);
    }

    public function store(StoreContactFaqRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (!isset($data['sort_order'])) {
            $data['sort_order'] = ((int) ContactFaq::query()->max('sort_order')) + 1;
        }

        $faq = ContactFaq::create($data);

        return response()->json([
            'ok' => true,
            'message' => 'Pregunta frecuente creada correctamente.',
            'data' => new ContactFaqResource($faq),
        ], 201);
    }

    public function show(ContactFaq $contactFaq): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'data' => new ContactFaqResource($contactFaq),
        ]);
    }

    public function update(UpdateContactFaqRequest $request, ContactFaq $contactFaq): JsonResponse
    {
        $contactFaq->update($request->validated());

        return response()->json([
            'ok' => true,
            'message' => 'Pregunta frecuente actualizada correctamente.',
            'data' => new ContactFaqResource($contactFaq->fresh()),
        ]);
    }

    public function destroy(ContactFaq $contactFaq): JsonResponse
    {
        $contactFaq->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Pregunta frecuente eliminada correctamente.',
        ]);
    }

    public function toggle(ContactFaq $contactFaq): JsonResponse
    {
        $contactFaq->update([
            'is_active' => ! $contactFaq->is_active,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Estado de la pregunta frecuente actualizado correctamente.',
            'data' => new ContactFaqResource($contactFaq->fresh()),
        ]);
    }

    public function reorder(ReorderContactFaqsRequest $request): JsonResponse
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('faqs') as $faqData) {
                ContactFaq::query()
                    ->whereKey($faqData['id'])
                    ->update(['sort_order' => $faqData['sort_order']]);
            }
        });

        return response()->json([
            'ok' => true,
            'message' => 'Orden de preguntas frecuentes actualizado correctamente.',
            'data' => ContactFaqResource::collection(ContactFaq::query()->ordered()->get()),
        ]);
    }
}
