<?php

namespace App\Http\Controllers\Api\V1\Account;

use App\Http\Controllers\Controller;
use App\Models\CashbackTransaction;
use App\Models\Order;
use App\Services\LoyaltyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashbackController extends Controller
{
    public function __construct(protected LoyaltyService $loyaltyService)
    {
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $settings = $this->loyaltyService->settings();

        return response()->json([
            'ok' => true,
            'message' => 'Resumen de cashback obtenido correctamente.',
            'data' => [
                'currency' => 'mxn',
                'settings' => [
                    'cashback_enabled' => (bool) $settings['cashback_enabled'],
                    'cashback_earn_percentage' => (float) $settings['cashback_earn_percentage'],
                    'cashback_redeem_enabled' => (bool) $settings['cashback_redeem_enabled'],
                    'cashback_max_redeem_percentage' => (float) $settings['cashback_max_redeem_percentage'],
                ],
                'balance' => [
                    'available' => $this->loyaltyService->availableCashback($user),
                    'pending' => $this->sum($user->id, CashbackTransaction::TYPE_CREDIT, CashbackTransaction::STATUS_PENDING)
                        - $this->sum($user->id, CashbackTransaction::TYPE_DEBIT, CashbackTransaction::STATUS_PENDING),
                ],
                'totals' => [
                    'earned' => $this->sum($user->id, CashbackTransaction::TYPE_CREDIT, [
                        CashbackTransaction::STATUS_AVAILABLE,
                        CashbackTransaction::STATUS_PENDING,
                    ]),
                    'used' => $this->sum($user->id, CashbackTransaction::TYPE_DEBIT, [
                        CashbackTransaction::STATUS_AVAILABLE,
                        CashbackTransaction::STATUS_PENDING,
                    ]),
                    'cancelled' => $this->sum($user->id, null, CashbackTransaction::STATUS_CANCELLED),
                    'transactions' => CashbackTransaction::query()
                        ->where('user_id', $user->id)
                        ->count(),
                ],
                'recent_transactions' => $this->transactionQuery($request)
                    ->limit(5)
                    ->get()
                    ->map(fn (CashbackTransaction $transaction) => $this->transactionPayload($transaction))
                    ->values(),
            ],
        ]);
    }

    public function transactions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:credit,debit'],
            'status' => ['nullable', 'string', 'in:available,pending,cancelled'],
            'order_id' => ['nullable', 'integer', 'min:1'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 15);
        $transactions = $this->transactionQuery($request)
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('type', $type))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->when($validated['order_id'] ?? null, fn ($query, int $orderId) => $query->where('order_id', $orderId))
            ->when($validated['from'] ?? null, fn ($query, string $from) => $query->whereDate('created_at', '>=', $from))
            ->when($validated['to'] ?? null, fn ($query, string $to) => $query->whereDate('created_at', '<=', $to))
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'ok' => true,
            'message' => 'Estado de cuenta de cashback obtenido correctamente.',
            'data' => $transactions->getCollection()
                ->map(fn (CashbackTransaction $transaction) => $this->transactionPayload($transaction))
                ->values(),
            'meta' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ],
            'filters' => [
                'types' => [
                    ['key' => CashbackTransaction::TYPE_CREDIT, 'label' => 'Generado'],
                    ['key' => CashbackTransaction::TYPE_DEBIT, 'label' => 'Usado'],
                ],
                'statuses' => [
                    ['key' => CashbackTransaction::STATUS_AVAILABLE, 'label' => 'Disponible'],
                    ['key' => CashbackTransaction::STATUS_PENDING, 'label' => 'Pendiente'],
                    ['key' => CashbackTransaction::STATUS_CANCELLED, 'label' => 'Cancelado'],
                ],
            ],
        ]);
    }

    protected function transactionQuery(Request $request)
    {
        return CashbackTransaction::query()
            ->where('user_id', $request->user()->id)
            ->with('order:id,number,status,payment_status,total,paid_at,created_at')
            ->latest('id');
    }

    protected function transactionPayload(CashbackTransaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type,
            'type_label' => $this->typeLabel((string) $transaction->type),
            'status' => $transaction->status,
            'status_label' => $this->statusLabel((string) $transaction->status),
            'amount' => (float) $transaction->amount,
            'signed_amount' => $transaction->type === CashbackTransaction::TYPE_DEBIT
                ? -1 * (float) $transaction->amount
                : (float) $transaction->amount,
            'description' => $transaction->description,
            'order' => $transaction->order ? $this->orderPayload($transaction->order) : null,
            'metadata' => $transaction->metadata ?? [],
            'created_at' => $transaction->created_at,
            'updated_at' => $transaction->updated_at,
        ];
    }

    protected function orderPayload(Order $order): array
    {
        return [
            'id' => $order->id,
            'number' => $order->number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'total' => (float) $order->total,
            'paid_at' => $order->paid_at,
            'created_at' => $order->created_at,
            'links' => [
                'detail' => "/api/v1/account/orders/{$order->id}",
            ],
        ];
    }

    protected function sum(int $userId, ?string $type, string|array $status): float
    {
        return round((float) CashbackTransaction::query()
            ->where('user_id', $userId)
            ->when($type !== null, fn ($query) => $query->where('type', $type))
            ->when(
                is_array($status),
                fn ($query) => $query->whereIn('status', $status),
                fn ($query) => $query->where('status', $status)
            )
            ->sum('amount'), 2);
    }

    protected function typeLabel(string $type): string
    {
        return match ($type) {
            CashbackTransaction::TYPE_DEBIT => 'Usado',
            default => 'Generado',
        };
    }

    protected function statusLabel(string $status): string
    {
        return match ($status) {
            CashbackTransaction::STATUS_PENDING => 'Pendiente',
            CashbackTransaction::STATUS_CANCELLED => 'Cancelado',
            default => 'Disponible',
        };
    }
}
