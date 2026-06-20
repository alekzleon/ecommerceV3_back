<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Cart\CartResource;
use App\Jobs\SendWhatsAppMessageJob;
use App\Mail\AbandonedCartMail;
use App\Models\Cart;
use App\Models\EcommerceSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

class CartController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(['active', 'abandoned'])],
            'search' => ['nullable', 'string', 'max:120'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Cart::query()
            ->with(['user:id,name,email', 'items:id,cart_id'])
            ->whereIn('status', ['active', 'abandoned'])
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['search'] ?? null, function ($query, $search) {
                $query->whereHas('user', function ($userQuery) use ($search) {
                    $userQuery->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest('updated_at');

        $carts = $query->paginate((int) ($validated['per_page'] ?? 15));

        return response()->json([
            'ok' => true,
            'data' => $carts->through(fn (Cart $cart) => $this->cartListPayload($cart)),
        ]);
    }

    public function show(Cart $cart): JsonResponse
    {
        $cart->load([
            'user.customerProfile',
            'user.defaultAddress',
            'user.addresses',
            'items.product.category',
            'items.product.family',
        ]);

        return response()->json([
            'ok' => true,
            'data' => [
                'cart' => new CartResource($cart),
                'customer' => [
                    'id' => $cart->user?->id,
                    'name' => $cart->user?->name,
                    'email' => $cart->user?->email,
                    'whatsapp' => $cart->user?->customerProfile?->whatsapp,
                ],
                'tracking' => $this->trackingPayload($cart),
            ],
        ]);
    }

    public function remind(Request $request, Cart $cart): JsonResponse
    {
        $validated = $request->validate([
            'channels' => ['nullable', 'array'],
            'channels.*' => ['required', Rule::in(['email', 'whatsapp'])],
        ]);

        $channels = collect($validated['channels'] ?? ['email', 'whatsapp'])->unique()->values();
        $sent = [
            'email' => false,
            'whatsapp' => false,
        ];

        if ($channels->contains('email')) {
            $this->sendReminderEmail($cart);
            $sent['email'] = true;
        }

        if ($channels->contains('whatsapp')) {
            $this->sendReminderWhatsApp($cart);
            $sent['whatsapp'] = true;
        }

        $cart->forceFill([
            'abandoned_notified_at' => $cart->abandoned_notified_at ?: now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => 'Recordatorio enviado correctamente.',
            'data' => [
                'cart_id' => $cart->id,
                'sent' => $sent,
                'tracking' => $this->trackingPayload($cart->fresh()),
            ],
        ]);
    }

    public function clear(Request $request, Cart $cart): JsonResponse
    {
        $validated = $request->validate([
            'archive' => ['nullable', 'boolean'],
        ]);

        $cart->items()->delete();

        $cart->forceFill([
            'items_count' => 0,
            'subtotal_snapshot' => 0,
            'discount_snapshot' => 0,
            'tax_snapshot' => 0,
            'total_snapshot' => 0,
            'status' => ($validated['archive'] ?? false) ? 'archived' : $cart->status,
            'metadata' => array_merge($cart->metadata ?? [], [
                'taxes' => [
                    'total' => 0.0,
                    'items' => [],
                ],
            ]),
            'last_activity_at' => now(),
        ])->save();

        return response()->json([
            'ok' => true,
            'message' => 'Carrito vaciado correctamente.',
            'data' => [
                'cart' => new CartResource($cart->fresh([
                    'user.defaultAddress',
                    'user.addresses',
                    'items.product.category',
                    'items.product.family',
                ])),
            ],
        ]);
    }

    protected function sendReminderEmail(Cart $cart): void
    {
        $cart->loadMissing(['user', 'items.product']);

        abort_if(blank($cart->user?->email), 422, 'El cliente no tiene correo registrado.');

        $recipient = config('services.testing_recipients.abandoned_cart_email') ?: $cart->user->email;

        Mail::to($recipient)->send(new AbandonedCartMail($cart, $this->recoverUrl($cart)));

        $cart->forceFill(['abandoned_email_sent_at' => now()])->save();
    }

    protected function sendReminderWhatsApp(Cart $cart): void
    {
        $link = rtrim(config('services.frontend.url'), '/') . '/carrito';
        $message = "Hola, vimos que dejaste productos en tu carrito 🛒\n\n"
            . "Aún están esperando por ti.\n"
            . "Recupera tu carrito aquí: {$link}";

        SendWhatsAppMessageJob::dispatch('+523332244005', $message);

        $cart->forceFill(['abandoned_whatsapp_sent_at' => now()])->save();
    }

    protected function recoverUrl(Cart $cart): string
    {
        $settings = EcommerceSetting::abandonedCartSettings();
        $expiresAt = now()->addHours((int) data_get($settings, 'recovery_link_expires_hours', 48));
        $recoverPath = URL::temporarySignedRoute(
            'cart.recover',
            $expiresAt,
            ['cart' => $cart->id],
            false
        );
        $backendRecoverUrl = rtrim((string) config('services.backend.url'), '/') . $recoverPath;

        return rtrim((string) config('services.frontend.url'), '/') . '/carrito?' . http_build_query([
            'cart_id' => $cart->id,
            'recover_url' => $backendRecoverUrl,
        ]);
    }

    protected function cartListPayload(Cart $cart): array
    {
        return [
            'id' => $cart->id,
            'status' => $cart->status,
            'customer' => [
                'id' => $cart->user?->id,
                'name' => $cart->user?->name,
                'email' => $cart->user?->email,
            ],
            'items_count' => (float) $cart->items_count,
            'items_rows_count' => $cart->items->count(),
            'subtotal' => (float) $cart->subtotal_snapshot,
            'discount' => (float) $cart->discount_snapshot,
            'tax' => (float) $cart->tax_snapshot,
            'total' => (float) $cart->total_snapshot,
            'last_activity_at' => $cart->last_activity_at?->toISOString(),
            'abandoned_at' => $cart->abandoned_at?->toISOString(),
            'created_at' => $cart->created_at?->toISOString(),
            'updated_at' => $cart->updated_at?->toISOString(),
            'tracking' => $this->trackingPayload($cart),
        ];
    }

    protected function trackingPayload(Cart $cart): array
    {
        return [
            'abandoned_notified_at' => $cart->abandoned_notified_at?->toISOString(),
            'abandoned_email_sent_at' => $cart->abandoned_email_sent_at?->toISOString(),
            'abandoned_whatsapp_sent_at' => $cart->abandoned_whatsapp_sent_at?->toISOString(),
            'recovered_at' => $cart->recovered_at?->toISOString(),
        ];
    }
}
