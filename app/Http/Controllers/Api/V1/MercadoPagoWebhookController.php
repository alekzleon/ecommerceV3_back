<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMercadoPagoWebhook;
use App\Models\TenantPaymentConnection;
use App\Services\Payments\MercadoPagoWebhookSignatureValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MercadoPagoWebhookController extends Controller
{
    public function __construct(private MercadoPagoWebhookSignatureValidator $signatures)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        if (! $this->signatures->isValid($request)) {
            Log::warning('Mercado Pago webhook rejected due to invalid signature.');

            return response()->json(['success' => false, 'error' => ['code' => 'INVALID_SIGNATURE']], 401);
        }

        $payload = $request->json()->all();
        if (data_get($payload, 'type') !== 'payment') {
            return response()->json(['success' => true, 'data' => ['result' => 'ignored']]);
        }

        $paymentId = $request->query('data.id', $request->query('data_id', data_get($payload, 'data.id')));
        $providerAccountId = data_get($payload, 'user_id');

        if (blank($paymentId) || blank($providerAccountId)) {
            return response()->json(['success' => true, 'data' => ['result' => 'ignored']]);
        }

        $connection = TenantPaymentConnection::query()
            ->where('provider', TenantPaymentConnection::PROVIDER_MERCADOPAGO)
            ->where('provider_account_id', (string) $providerAccountId)
            ->first();

        if (! $connection) {
            return response()->json(['success' => true, 'data' => ['result' => 'ignored']]);
        }

        ProcessMercadoPagoWebhook::dispatch($connection->tenant_id, (string) $paymentId);

        return response()->json(['success' => true, 'data' => ['result' => 'accepted']]);
    }
}
