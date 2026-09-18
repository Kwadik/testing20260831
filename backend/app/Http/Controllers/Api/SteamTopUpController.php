<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSteamTopUpRequest;
use App\Services\SteamTopUpService;
use Illuminate\Http\JsonResponse;

class SteamTopUpController extends Controller
{
    public function __construct(
        private readonly SteamTopUpService $steamTopUpService,
    ) {
    }

    public function store(
        StoreSteamTopUpRequest $request,
    ): JsonResponse {
        $order = $this->steamTopUpService->create(
            amount: $request->validated('amount'),
            currency: $request->validated('currency'),
            promoCode: $request->validated('promo_code'),
        );

        return response()->json([
            'data' => [
                'id' => $order->public_id,
                'status' => $order->status->value,
                'sku' => $order->sku,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'promo_code' => $order->promo_code,
                'discount_amount' => $order->discount_amount,
            ],
        ], 201);
    }
}
