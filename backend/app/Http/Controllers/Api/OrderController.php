<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {
    }
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->orderService->create(
            $request->validated('sku'),
        );

        return response()->json([
            'data' => [
                'id' => $order->public_id,
                'status' => $order->status->value,
                'sku' => $order->sku,
                'amount' => $order->amount,
                'currency' => $order->currency,
            ],
        ], 201);
    }
}
