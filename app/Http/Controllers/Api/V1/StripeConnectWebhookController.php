<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripeConnectWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeConnectWebhookController extends Controller
{
    public function __construct(
        protected StripeConnectWebhookService $stripeConnectWebhookService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->stripeConnectWebhookService->handle(
            payload: $request->getContent(),
            signatureHeader: $request->header('Stripe-Signature')
        );

        return response()->json($result);
    }
}
