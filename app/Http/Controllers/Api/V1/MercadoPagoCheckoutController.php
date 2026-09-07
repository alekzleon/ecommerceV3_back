<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Payments\PaymentGatewayFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MercadoPagoCheckoutController extends Controller
{
    public function __construct(private PaymentGatewayFactory $gateways)
    {
    }

    public function checkout(Request $request, Order $order): JsonResponse
    {
        abort_unless((int) $order->user_id === (int) $request->user()->id, 403, 'No tienes acceso a este pedido.');

        return $this->startCheckout($request, $order);
    }

    public function guestCheckout(Request $request, Order $order): JsonResponse
    {
        $guestToken = $request->header('X-Guest-Token')
            ?: $request->input('guest_token')
            ?: $request->query('guest_token');

        abort_unless(is_string($guestToken) && $guestToken !== '', 422, 'El token de carrito invitado es obligatorio.');
        abort_unless(hash_equals((string) $order->guest_token, $guestToken), 403, 'No tienes acceso a este pedido.');

        return $this->startCheckout($request, $order);
    }

    protected function startCheckout(Request $request, Order $order): JsonResponse
    {
        $tenantHost = (string) $request->attributes->get('tenant_host');
        $origin = $this->storefrontOrigin($request, $tenantHost) ?: "https://{$tenantHost}";
        $gateway = $this->gateways->make(tenant(), 'mercadopago');

        return response()->json([
            'success' => true,
            'data' => $gateway->createPreference($order, $origin),
        ]);
    }

    protected function storefrontOrigin(Request $request, string $tenantHost): ?string
    {
        $origin = $request->headers->get('Origin') ?: $request->headers->get('X-Store-Origin');

        if (! is_string($origin) || trim($origin) === '') {
            return null;
        }

        $scheme = parse_url($origin, PHP_URL_SCHEME);
        $host = parse_url($origin, PHP_URL_HOST);
        $port = parse_url($origin, PHP_URL_PORT);

        if (! in_array($scheme, ['http', 'https'], true) || $host !== $tenantHost) {
            return null;
        }

        return $scheme.'://'.$host.($port ? ':'.$port : '');
    }
}
