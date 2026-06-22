<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CartStatus;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CashbackTransaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Services\SalesChannelService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function __construct(protected SalesChannelService $salesChannelService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);

        return response()->json([
            'ok' => true,
            'data' => [
                'filters' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ],
                'summary' => $this->summary($from, $to),
                'charts' => [
                    'sales_by_day' => $this->salesByDay($from, $to),
                    'orders_by_status' => $this->ordersByStatus($from, $to),
                    'cashback_by_day' => $this->cashbackByDay($from, $to),
                    'cart_funnel' => $this->cartFunnel($from, $to),
                ],
                'tables' => [
                    'top_products' => $this->bestSellingProducts($from, $to),
                    'best_selling_products' => $this->bestSellingProducts($from, $to),
                    'least_selling_products' => $this->leastSellingProducts($from, $to),
                    'low_stock_products' => $this->lowStockProducts($from, $to),
                    'recent_orders' => $this->recentOrders($from, $to),
                ],
            ],
        ]);
    }

    public function salesChannels(Request $request): JsonResponse
    {
        [$from, $to] = $this->dateRange($request);
        $channel = $this->salesChannelService->normalize($request->string('sales_channel')->toString());
        $limit = max(1, min((int) $request->integer('limit', 5), 20));
        $channels = $this->salesByChannel($from, $to);

        return response()->json([
            'ok' => true,
            'message' => 'Dashboard de canales de venta obtenido correctamente.',
            'data' => [
                'filters' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                    'sales_channel' => $channel,
                    'limit' => $limit,
                ],
                'summary' => $this->salesChannelSummary($channels),
                'channels' => $channels,
                'top_products_by_channel' => $this->topProductsByChannel($from, $to, $limit, $channel),
                'accepted_channels' => collect(SalesChannelService::ALLOWED_CHANNELS)
                    ->map(fn ($acceptedChannel) => [
                        'value' => $acceptedChannel,
                        'label' => $this->salesChannelService->label($acceptedChannel),
                    ])
                    ->values()
                    ->all(),
            ],
        ]);
    }

    protected function summary(Carbon $from, Carbon $to): array
    {
        $paidOrders = $this->paidOrders($from, $to);
        $sales = (float) (clone $paidOrders)->sum('total');
        $orders = (int) (clone $paidOrders)->count();
        $discounts = (float) (clone $paidOrders)->sum('discount');

        $cashbackEarned = (float) CashbackTransaction::query()
            ->where('type', CashbackTransaction::TYPE_CREDIT)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $cashbackRedeemed = (float) CashbackTransaction::query()
            ->where('type', CashbackTransaction::TYPE_DEBIT)
            ->whereBetween('created_at', [$from, $to])
            ->sum('amount');

        $availableCashback = (float) CashbackTransaction::query()
            ->where('status', CashbackTransaction::STATUS_AVAILABLE)
            ->selectRaw("SUM(CASE WHEN type = ? THEN amount ELSE -amount END) as balance", [
                CashbackTransaction::TYPE_CREDIT,
            ])
            ->value('balance');

        return [
            'sales' => round($sales, 2),
            'orders' => $orders,
            'average_order_value' => $orders > 0 ? round($sales / $orders, 2) : 0.0,
            'discounts' => round($discounts, 2),
            'customers_total' => (int) User::query()->where('role_id', User::ROLE_CLIENTE)->count(),
            'customers_new' => (int) User::query()
                ->where('role_id', User::ROLE_CLIENTE)
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'customers_with_purchase' => (int) (clone $paidOrders)->distinct('user_id')->count('user_id'),
            'products_total' => (int) Product::query()->count(),
            'products_active' => (int) Product::query()->where('is_active', true)->count(),
            'pending_orders' => (int) Order::query()
                ->where('status', Order::STATUS_PENDING_PAYMENT)
                ->whereBetween('created_at', [$from, $to])
                ->count(),
            'active_carts' => (int) Cart::query()
                ->where('status', CartStatus::ACTIVE->value)
                ->count(),
            'abandoned_carts' => (int) Cart::query()
                ->where('status', CartStatus::ABANDONED->value)
                ->count(),
            'cashback_earned' => round($cashbackEarned, 2),
            'cashback_redeemed' => round($cashbackRedeemed, 2),
            'cashback_available_balance' => round($availableCashback, 2),
            'estimated_customer_savings' => round($discounts + $cashbackRedeemed, 2),
        ];
    }

    protected function salesByDay(Carbon $from, Carbon $to): array
    {
        $rows = $this->paidOrders($from, $to)
            ->selectRaw('DATE(COALESCE(paid_at, created_at)) as date')
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('SUM(total) as sales')
            ->selectRaw('SUM(discount) as discounts')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($rows) {
                $key = $date->toDateString();
                $row = $rows->get($key);

                return [
                    'date' => $key,
                    'orders' => (int) ($row->orders ?? 0),
                    'sales' => round((float) ($row->sales ?? 0), 2),
                    'discounts' => round((float) ($row->discounts ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    protected function ordersByStatus(Carbon $from, Carbon $to): array
    {
        return Order::query()
            ->whereBetween('created_at', [$from, $to])
            ->select('status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(total) as total')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
                'total' => round((float) $row->total, 2),
            ])
            ->values()
            ->all();
    }

    protected function cashbackByDay(Carbon $from, Carbon $to): array
    {
        $rows = CashbackTransaction::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw("SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as earned", [CashbackTransaction::TYPE_CREDIT])
            ->selectRaw("SUM(CASE WHEN type = ? THEN amount ELSE 0 END) as redeemed", [CashbackTransaction::TYPE_DEBIT])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        return collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))
            ->map(function (Carbon $date) use ($rows) {
                $key = $date->toDateString();
                $row = $rows->get($key);

                return [
                    'date' => $key,
                    'earned' => round((float) ($row->earned ?? 0), 2),
                    'redeemed' => round((float) ($row->redeemed ?? 0), 2),
                ];
            })
            ->values()
            ->all();
    }

    protected function cartFunnel(Carbon $from, Carbon $to): array
    {
        return [
            [
                'status' => CartStatus::ACTIVE->value,
                'count' => (int) Cart::query()
                    ->where('status', CartStatus::ACTIVE->value)
                    ->count(),
            ],
            [
                'status' => CartStatus::ABANDONED->value,
                'count' => (int) Cart::query()
                    ->where('status', CartStatus::ABANDONED->value)
                    ->count(),
            ],
        ];
    }

    protected function bestSellingProducts(Carbon $from, Carbon $to): array
    {
        return $this->soldProductsBaseQuery($from, $to)
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row) => $this->productSalesPayload($row))
            ->values()
            ->all();
    }

    protected function leastSellingProducts(Carbon $from, Carbon $to): array
    {
        return $this->soldProductsBaseQuery($from, $to)
            ->orderBy('quantity')
            ->orderBy('revenue')
            ->limit(10)
            ->get()
            ->map(fn ($row) => $this->productSalesPayload($row))
            ->values()
            ->all();
    }

    protected function lowStockProducts(Carbon $from, Carbon $to): array
    {
        $salesSubquery = $this->soldProductsBaseQuery($from, $to);

        return Product::query()
            ->leftJoinSub($salesSubquery, 'sales', function ($join) {
                $join->on('sales.product_id', '=', 'products.id');
            })
            ->select('products.id', 'products.name', 'products.sku', 'products.stock', 'products.is_active')
            ->selectRaw('COALESCE(sales.quantity, 0) as quantity_sold')
            ->selectRaw('COALESCE(sales.revenue, 0) as revenue')
            ->where('products.is_active', true)
            ->orderBy('products.stock')
            ->orderByDesc('quantity_sold')
            ->limit(10)
            ->get()
            ->map(fn ($row) => [
                'product_id' => (int) $row->id,
                'name' => $row->name,
                'sku' => $row->sku,
                'stock' => (float) $row->stock,
                'quantity_sold' => (float) $row->quantity_sold,
                'revenue' => round((float) $row->revenue, 2),
                'is_active' => (bool) $row->is_active,
            ])
            ->values()
            ->all();
    }

    protected function recentOrders(Carbon $from, Carbon $to): array
    {
        return Order::query()
            ->with('user:id,name,email')
            ->whereBetween('created_at', [$from, $to])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (Order $order) => [
                'id' => $order->id,
                'number' => $order->number,
                'customer' => [
                    'id' => $order->user?->id,
                    'name' => $order->user?->name,
                    'email' => $order->user?->email,
                ],
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'sales_channel' => $order->sales_channel ?: SalesChannelService::DEFAULT_CHANNEL,
                'sales_channel_label' => $this->salesChannelService->label($order->sales_channel),
                'total' => (float) $order->total,
                'created_at' => $order->created_at?->toISOString(),
                'paid_at' => $order->paid_at?->toISOString(),
            ])
            ->values()
            ->all();
    }

    protected function paidOrders(Carbon $from, Carbon $to)
    {
        return Order::query()
            ->where('status', Order::STATUS_PAID)
            ->where('payment_status', Order::PAYMENT_PAID)
            ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$from, $to]);
    }

    protected function salesByChannel(Carbon $from, Carbon $to): array
    {
        $totalSales = (float) $this->paidOrders($from, $to)->sum('total');
        $totalOrders = (int) $this->paidOrders($from, $to)->count();

        return $this->paidOrders($from, $to)
            ->selectRaw("COALESCE(NULLIF(sales_channel, ''), 'online_store') as sales_channel")
            ->selectRaw('COUNT(*) as orders')
            ->selectRaw('COUNT(DISTINCT user_id) as customers')
            ->selectRaw('SUM(total) as sales')
            ->selectRaw('SUM(discount) as discounts')
            ->selectRaw('AVG(total) as average_order_value')
            ->groupBy(DB::raw("COALESCE(NULLIF(sales_channel, ''), 'online_store')"))
            ->orderByDesc('sales')
            ->get()
            ->map(function ($row) use ($totalSales, $totalOrders) {
                $sales = round((float) $row->sales, 2);
                $orders = (int) $row->orders;
                $channel = $row->sales_channel ?: SalesChannelService::DEFAULT_CHANNEL;

                return [
                    'sales_channel' => $channel,
                    'sales_channel_label' => $this->salesChannelService->label($channel),
                    'orders' => $orders,
                    'customers' => (int) $row->customers,
                    'sales' => $sales,
                    'discounts' => round((float) $row->discounts, 2),
                    'average_order_value' => round((float) $row->average_order_value, 2),
                    'sales_percentage' => $totalSales > 0 ? round(($sales / $totalSales) * 100, 2) : 0.0,
                    'orders_percentage' => $totalOrders > 0 ? round(($orders / $totalOrders) * 100, 2) : 0.0,
                ];
            })
            ->values()
            ->all();
    }

    protected function salesChannelSummary(array $channels): array
    {
        $collection = collect($channels);
        $sales = round((float) $collection->sum('sales'), 2);
        $orders = (int) $collection->sum('orders');
        $topChannel = $collection->sortByDesc('sales')->first();

        return [
            'sales' => $sales,
            'orders' => $orders,
            'average_order_value' => $orders > 0 ? round($sales / $orders, 2) : 0.0,
            'channels_count' => $collection->count(),
            'top_channel' => $topChannel ? [
                'sales_channel' => $topChannel['sales_channel'],
                'sales_channel_label' => $topChannel['sales_channel_label'],
                'sales' => $topChannel['sales'],
                'orders' => $topChannel['orders'],
            ] : null,
        ];
    }

    protected function topProductsByChannel(Carbon $from, Carbon $to, int $limit = 5, ?string $channel = null): array
    {
        $rows = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', Order::STATUS_PAID)
            ->where('orders.payment_status', Order::PAYMENT_PAID)
            ->whereBetween(DB::raw('COALESCE(orders.paid_at, orders.created_at)'), [$from, $to])
            ->when($channel, fn ($query) => $query->where('orders.sales_channel', $channel))
            ->select('order_items.product_id', 'order_items.name_snapshot', 'order_items.sku_snapshot')
            ->selectRaw("COALESCE(NULLIF(orders.sales_channel, ''), 'online_store') as sales_channel")
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy(DB::raw("COALESCE(NULLIF(orders.sales_channel, ''), 'online_store')"))
            ->groupBy('order_items.product_id', 'order_items.name_snapshot', 'order_items.sku_snapshot')
            ->orderBy('sales_channel')
            ->orderByDesc('revenue')
            ->get()
            ->groupBy('sales_channel');

        return $rows
            ->map(function ($products, string $salesChannel) use ($limit) {
                return [
                    'sales_channel' => $salesChannel,
                    'sales_channel_label' => $this->salesChannelService->label($salesChannel),
                    'products' => $products
                        ->take($limit)
                        ->map(fn ($row) => $this->productSalesPayload($row))
                        ->values()
                        ->all(),
                ];
            })
            ->sortBy(fn ($row) => array_search($row['sales_channel'], SalesChannelService::ALLOWED_CHANNELS, true))
            ->values()
            ->all();
    }

    protected function soldProductsBaseQuery(Carbon $from, Carbon $to)
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', Order::STATUS_PAID)
            ->where('orders.payment_status', Order::PAYMENT_PAID)
            ->whereBetween(DB::raw('COALESCE(orders.paid_at, orders.created_at)'), [$from, $to])
            ->select('order_items.product_id', 'order_items.name_snapshot', 'order_items.sku_snapshot')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.line_total) as revenue')
            ->groupBy('order_items.product_id', 'order_items.name_snapshot', 'order_items.sku_snapshot');
    }

    protected function productSalesPayload($row): array
    {
        return [
            'product_id' => $row->product_id ? (int) $row->product_id : null,
            'name' => $row->name_snapshot,
            'sku' => $row->sku_snapshot,
            'quantity' => (float) $row->quantity,
            'revenue' => round((float) $row->revenue, 2),
        ];
    }

    protected function dateRange(Request $request): array
    {
        $to = $request->filled('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $from = $request->filled('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }
}
