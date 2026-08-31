<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentWebhookRequest;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentService $paymentService,
    ) {
    }

    public function handle(PaymentWebhookRequest $request): JsonResponse
    {
        $this->paymentService->handle(
            $request->validated(),
        );

        return response()->json([
            'status' => 'accepted',
        ]);
    }
}
