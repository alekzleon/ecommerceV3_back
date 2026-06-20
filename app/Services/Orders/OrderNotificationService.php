<?php

namespace App\Services\Orders;

use App\Jobs\SendWhatsAppMessageJob;
use App\Mail\OrderPurchaseSummaryMail;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class OrderNotificationService
{
    public function sendPurchaseNotifications(Order $order): void
    {
        $order->loadMissing(['user.customerProfile', 'user.customerPfrProfile', 'user.defaultAddress', 'items', 'payments']);

        $metadata = $order->metadata ?? [];
        $notifications = data_get($metadata, 'notifications', []);

        if (! data_get($notifications, 'purchase_email.sent_at')) {
            $sent = $this->sendPurchaseEmail($order);

            if ($sent) {
                data_set($metadata, 'notifications.purchase_email.sent_at', now()->toISOString());
                data_set($metadata, 'notifications.purchase_email.to', $order->user?->email);
            }
        }

        if (! data_get($notifications, 'sales_purchase_email.sent_at')) {
            $salesRecipient = 'alekzleon03.aa@gmail.com';
            $sent = $this->sendPurchaseEmail($order, $salesRecipient, false);

            if ($sent) {
                data_set($metadata, 'notifications.sales_purchase_email.sent_at', now()->toISOString());
                data_set($metadata, 'notifications.sales_purchase_email.to', $salesRecipient);
            }
        }

        if (! data_get($notifications, 'purchase_whatsapp.queued_at')) {
            $queued = $this->queuePurchaseWhatsApp($order);

            if ($queued) {
                data_set($metadata, 'notifications.purchase_whatsapp.queued_at', now()->toISOString());
                data_set($metadata, 'notifications.purchase_whatsapp.to', $this->resolveWhatsAppNumber($order));
            }
        }

        if ($metadata !== ($order->metadata ?? [])) {
            $order->forceFill(['metadata' => $metadata])->save();
        }
    }

    public function sendPurchaseEmail(Order $order, ?string $to = null, bool $markSent = true): bool
    {
        $order->loadMissing(['user', 'items', 'payments']);
        $recipient = $to ?: $order->user?->email;

        if (blank($recipient)) {
            Log::warning('Order purchase email skipped: missing recipient.', [
                'order_id' => $order->id,
                'order_number' => $order->number,
            ]);

            return false;
        }

        try {
            Mail::to($recipient)->send(new OrderPurchaseSummaryMail($order));

            if ($markSent) {
                $metadata = $order->metadata ?? [];
                data_set($metadata, 'notifications.purchase_email.sent_at', now()->toISOString());
                data_set($metadata, 'notifications.purchase_email.to', $recipient);
                $order->forceFill(['metadata' => $metadata])->save();
            }

            return true;
        } catch (\Throwable $exception) {
            Log::error('Order purchase email failed.', [
                'order_id' => $order->id,
                'order_number' => $order->number,
                'to' => $recipient,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    public function queuePurchaseWhatsApp(Order $order): bool
    {
        $order->loadMissing(['user.customerProfile', 'user.customerPfrProfile', 'user.defaultAddress']);
        $to = $this->resolveWhatsAppNumber($order);

        if (blank($to)) {
            Log::warning('Order purchase WhatsApp skipped: missing phone.', [
                'order_id' => $order->id,
                'order_number' => $order->number,
            ]);

            return false;
        }

        $body = implode("\n", [
            'Gracias por tu compra en Cloudi Shop.',
            'Tu número de orden es: ' . $order->number,
            'Puedes revisar el detalle y seguimiento siempre en Cloudi Shop.',
        ]);

        SendWhatsAppMessageJob::dispatch($to, $body);

        return true;
    }

    private function resolveWhatsAppNumber(Order $order): ?string
    {
        return '+523332244005';
    }
}
