<?php

namespace App\Console\Commands;

use App\Enums\CartStatus;
use App\Jobs\SendWhatsAppMessageJob;
use App\Mail\CartReadyReminderMail;
use App\Mail\PurchaseReminderMail;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendDailyReorderReminders extends Command
{
    protected $signature = 'purchases:daily-reorder-reminders {--days=7 : Days after last paid purchase}';
    protected $description = 'Sends purchase reminders and prepares repeat carts for customers after their last purchase.';

    public function __construct(
        protected CartService $cartService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $cutoff = now()->subDays($days);
        $processed = 0;

        Order::query()
            ->with(['user.customerProfile', 'user.customerPfrProfile', 'user.defaultAddress', 'items.product'])
            ->where('status', Order::STATUS_PAID)
            ->where('payment_status', Order::PAYMENT_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', $cutoff)
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('orders as newer_orders')
                    ->whereColumn('newer_orders.user_id', 'orders.user_id')
                    ->where('newer_orders.status', Order::STATUS_PAID)
                    ->where('newer_orders.payment_status', Order::PAYMENT_PAID)
                    ->whereNotNull('newer_orders.paid_at')
                    ->whereColumn('newer_orders.paid_at', '>', 'orders.paid_at');
            })
            ->orderBy('id')
            ->chunkById(100, function ($orders) use (&$processed) {
                foreach ($orders as $order) {
                    $this->processOrder($order);
                    $processed++;
                }
            });

        $this->info("Recordatorios procesados: {$processed}");

        return self::SUCCESS;
    }

    protected function processOrder(Order $order): void
    {
        if (! $order->user) {
            return;
        }

        $metadata = $order->metadata ?? [];

        if (! data_get($metadata, 'reorder.purchase_reminder_sent_at')) {
            $this->sendPurchaseReminder($order);
            data_set($metadata, 'reorder.purchase_reminder_sent_at', now()->toISOString());
        }

        if (! data_get($metadata, 'reorder.cart_ready_reminder_sent_at')) {
            [$cart, $created] = $this->resolveOrCreateRepeatCart($order);

            if ($cart) {
                $this->sendCartReadyReminder($order, $cart, $created);
                data_set($metadata, 'reorder.cart_ready_reminder_sent_at', now()->toISOString());
                data_set($metadata, 'reorder.cart_id', $cart->id);
                data_set($metadata, 'reorder.cart_created_from_last_purchase', $created);
            }
        }

        $order->forceFill(['metadata' => $metadata])->save();
    }

    protected function resolveOrCreateRepeatCart(Order $order): array
    {
        $existingCart = Cart::query()
            ->with(['items.product', 'user'])
            ->where('user_id', $order->user_id)
            ->where('status', CartStatus::ACTIVE->value)
            ->whereHas('items')
            ->latest('id')
            ->first();

        if ($existingCart) {
            return [$existingCart, false];
        }

        $pendingOrder = Order::query()
            ->with(['cart.items.product', 'user'])
            ->where('user_id', $order->user_id)
            ->where('status', Order::STATUS_PENDING_PAYMENT)
            ->where('payment_status', Order::PAYMENT_PENDING)
            ->latest('id')
            ->first();

        if ($pendingOrder?->cart) {
            return [$pendingOrder->cart, false];
        }

        $cart = null;

        foreach ($order->items as $item) {
            $product = $item->product instanceof Product ? $item->product : null;

            if (! $product || ! (bool) $product->is_active) {
                continue;
            }

            $cart = $this->cartService->addItem(
                user: $order->user,
                product: $product,
                quantity: (float) $item->quantity
            );
        }

        if (! $cart) {
            Log::warning('Repeat cart could not be created: no active products.', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ]);

            return [null, false];
        }

        $cartMetadata = $cart->metadata ?? [];
        $cartMetadata['reorder'] = [
            'source_order_id' => $order->id,
            'source_order_number' => $order->number,
            'created_at' => now()->toISOString(),
        ];

        $cart->forceFill([
            'source' => 'reorder',
            'metadata' => $cartMetadata,
        ])->save();

        return [$cart->fresh(['items.product', 'user']), true];
    }

    protected function sendPurchaseReminder(Order $order): void
    {
        $shopUrl = rtrim(config('services.frontend.url'), '/') . '/productos';
        $this->queueEmail($order->user, new PurchaseReminderMail($order, $shopUrl), 'purchase reminder');

        $message = implode("\n", [
            'Hola ' . ($order->user?->name ?? 'cliente') . ', ya pasaron algunos días desde tu última compra.',
            'Puedes volver a surtir tus productos aquí:',
            $shopUrl,
        ]);

        $this->queueWhatsApp($order, $message, 'purchase reminder');
    }

    protected function sendCartReadyReminder(Order $order, Cart $cart, bool $created): void
    {
        $cartUrl = rtrim(config('services.frontend.url'), '/') . '/carrito';
        $this->queueEmail($order->user, new CartReadyReminderMail($cart, $cartUrl, $created), 'cart ready reminder');

        $message = implode("\n", [
            'Hola ' . ($order->user?->name ?? 'cliente') . ', tu carrito está listo para tu compra.',
            $created ? 'Lo preparamos con productos de tu última compra.' : 'Ya tenías un carrito pendiente listo para continuar.',
            'Puedes revisarlo aquí:',
            $cartUrl,
        ]);

        $this->queueWhatsApp($order, $message, 'cart ready reminder');
    }

    protected function queueEmail(?User $user, $mail, string $context): void
    {
        if (blank($user?->email)) {
            Log::warning("Daily reorder {$context} email skipped: missing recipient.", [
                'user_id' => $user?->id,
            ]);

            return;
        }

        Mail::to($user->email)->queue($mail);
    }

    protected function queueWhatsApp(Order $order, string $message, string $context): void
    {
        $to = $this->resolveWhatsAppNumber($order);

        if (blank($to)) {
            Log::warning("Daily reorder {$context} WhatsApp skipped: missing phone.", [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ]);

            return;
        }

        SendWhatsAppMessageJob::dispatch($to, $message);
    }

    protected function resolveWhatsAppNumber(Order $order): ?string
    {
        return '+523332244005';
    }
}
