<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payments\StripePaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StripeWebhookController extends Controller
{
    public function __construct(
        protected StripePaymentService $stripePaymentService
    ) {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $result = $this->stripePaymentService->handleWebhook(
            payload: $request->getContent(),
            signatureHeader: $request->header('Stripe-Signature')
        );

        return response()->json($result);
    }
}
