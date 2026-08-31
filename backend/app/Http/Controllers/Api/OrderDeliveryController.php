<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;

class OrderDeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {
    }

    public function deliver(string $publicId): JsonResponse
    {
        $order = Order::where('public_id', $publicId)->firstOrFail();

        $inventory = $this->deliveryService->deliver($order);

        return response()->json([
            'status' => 'delivered',
            'code' => $inventory->code,
        ]);
    }
}
