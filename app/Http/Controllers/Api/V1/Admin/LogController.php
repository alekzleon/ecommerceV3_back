<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 20);
        $perPage = $perPage > 0 ? min($perPage, 100) : 20;

        $logs = ActivityLog::query()
            ->with('user:id,name,email,username')
            ->when($request->filled('module'), fn ($query) => $query->where('module', $request->string('module')->toString()))
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('actor_type'), fn ($query) => $query->where('actor_type', $request->string('actor_type')->toString()))
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', (int) $request->integer('user_id')))
            ->when($request->filled('entity_type'), fn ($query) => $query->where('entity_type', $request->string('entity_type')->toString()))
            ->when($request->filled('entity_id'), fn ($query) => $query->where('entity_id', (int) $request->integer('entity_id')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));

                $query->where(function ($subQuery) use ($search) {
                    $subQuery->where('summary', 'like', "%{$search}%")
                        ->orWhere('module', 'like', "%{$search}%")
                        ->orWhere('action', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%")
                                ->orWhere('username', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Logs obtenidos correctamente.',
            'data' => $logs->getCollection()->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'actor_type' => $log->actor_type,
                'actor' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'username' => $log->user->username,
                ] : null,
                'module' => $log->module,
                'action' => $log->action,
                'summary' => $log->summary,
                'entity' => [
                    'type' => $log->entity_type,
                    'id' => $log->entity_id,
                ],
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'metadata' => $log->metadata,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
                'from' => $logs->firstItem(),
                'to' => $logs->lastItem(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        abort(405);
    }

    public function show(ActivityLog $log): JsonResponse
    {
        $log->load('user:id,name,email,username');

        return response()->json([
            'ok' => true,
            'message' => 'Log obtenido correctamente.',
            'data' => [
                'id' => $log->id,
                'actor_type' => $log->actor_type,
                'actor' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                    'username' => $log->user->username,
                ] : null,
                'module' => $log->module,
                'action' => $log->action,
                'summary' => $log->summary,
                'entity' => [
                    'type' => $log->entity_type,
                    'id' => $log->entity_id,
                ],
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'metadata' => $log->metadata,
                'ip' => $log->ip,
                'user_agent' => $log->user_agent,
                'created_at' => $log->created_at,
            ],
        ]);
    }

    public function update(Request $request, string $id)
    {
        abort(405);
    }

    public function destroy(string $id)
    {
        abort(405);
    }
}
