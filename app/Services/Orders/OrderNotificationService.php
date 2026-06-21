<?php

namespace App\Services\Orders;

use App\Jobs\SendWhatsAppMessageJob;
use App\Mail\OrderPurchaseSummaryMail;
use App\Models\EcommerceSetting;
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
        $saleNotifications = EcommerceSetting::saleNotificationSettings();

        if (! data_get($notifications, 'purchase_email.sent_at')) {
            $sent = $this->sendPurchaseEmail($order);

            if ($sent) {
                data_set($metadata, 'notifications.purchase_email.sent_at', now()->toISOString());
                data_set($metadata, 'notifications.purchase_email.to', $order->user?->email);
            }
        }

        if (
            (bool) data_get($saleNotifications, 'enabled', true)
            && (bool) data_get($saleNotifications, 'send_email', true)
            && ! data_get($notifications, 'admin_purchase_email.sent_at')
        ) {
            $adminRecipient = $this->resolveAdminEmail($saleNotifications);
            $sent = $this->sendPurchaseEmail($order, $adminRecipient, false, true);

            if ($sent) {
                data_set($metadata, 'notifications.admin_purchase_email.sent_at', now()->toISOString());
                data_set($metadata, 'notifications.admin_purchase_email.to', $adminRecipient);
            }
        }

        if (! data_get($notifications, 'purchase_whatsapp.queued_at')) {
            $queued = $this->queuePurchaseWhatsApp($order);

            if ($queued) {
                data_set($metadata, 'notifications.purchase_whatsapp.queued_at', now()->toISOString());
                data_set($metadata, 'notifications.purchase_whatsapp.to', $this->resolveWhatsAppNumber($order));
            }
        }

        if (
            (bool) data_get($saleNotifications, 'enabled', true)
            && (bool) data_get($saleNotifications, 'send_whatsapp', true)
            && ! data_get($notifications, 'admin_purchase_whatsapp.queued_at')
        ) {
            $queued = $this->queueAdminPurchaseWhatsApp($order, $saleNotifications);

            if ($queued) {
                data_set($metadata, 'notifications.admin_purchase_whatsapp.queued_at', now()->toISOString());
                data_set($metadata, 'notifications.admin_purchase_whatsapp.to', $this->resolveAdminWhatsAppNumber($saleNotifications));
            }
        }

        if ($metadata !== ($order->metadata ?? [])) {
            $order->forceFill(['metadata' => $metadata])->save();
        }
    }

    public function sendPurchaseEmail(
        Order $order,
        ?string $to = null,
        bool $markSent = true,
        bool $adminNotification = false
    ): bool {
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
            Mail::to($recipient)->send(new OrderPurchaseSummaryMail($order, $adminNotification));

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

        $lines = [
            'Gracias por tu compra en Cloudi Shop.',
            'Tu número de orden es: ' . $order->number,
        ];

        if (filled($order->document_notes)) {
            $lines[] = 'Notas: ' . $order->document_notes;
        }

        $lines[] = 'Puedes revisar el detalle y seguimiento siempre en Cloudi Shop.';

        $body = implode("\n", $lines);

        SendWhatsAppMessageJob::dispatch($to, $body);

        return true;
    }

    public function queueAdminPurchaseWhatsApp(Order $order, ?array $settings = null): bool
    {
        $order->loadMissing(['user', 'items']);
        $to = $this->resolveAdminWhatsAppNumber($settings);

        if (blank($to)) {
            Log::warning('Admin order WhatsApp skipped: missing phone.', [
                'order_id' => $order->id,
                'order_number' => $order->number,
            ]);

            return false;
        }

        $body = sprintf(
            'Tienes una nueva orden en tu tienda en linea de %d items por $%s MXN - Num Orden: %s',
            (int) $order->items_count,
            number_format((float) $order->total, 2),
            $order->number
        );

        SendWhatsAppMessageJob::dispatch($to, $body);

        return true;
    }

    private function resolveWhatsAppNumber(Order $order): ?string
    {
        return '+523332244005';
    }

    private function resolveAdminEmail(?array $settings = null): ?string
    {
        $settings ??= EcommerceSetting::saleNotificationSettings();

        return data_get($settings, 'admin_email')
            ?: config('mail.from.address');
    }

    private function resolveAdminWhatsAppNumber(?array $settings = null): ?string
    {
        $settings ??= EcommerceSetting::saleNotificationSettings();
        $number = preg_replace('/\D+/', '', (string) data_get($settings, 'admin_whatsapp', '9612819842'));

        if (blank($number)) {
            return null;
        }

        if (str_starts_with($number, '52')) {
            return '+' . $number;
        }

        return '+52' . $number;
    }
}
