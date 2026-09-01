<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderDeliveryController extends Controller
{
    public function __construct(
        private readonly DeliveryService $deliveryService,
    ) {
    }

    public function deliver(Request $request, string $publicId): JsonResponse
    {
        $order = Order::where('public_id', $publicId)->firstOrFail();

        $requestId = $request->header('Idempotency-Key');

        if (! $requestId) {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 422);
        }

        try {
            $inventory = $this->deliveryService->deliver(
                $order,
                $requestId,
            );
        } catch (IdempotencyKeyConflictException|OutOfStockException $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'status' => 'delivered',
            'code' => $inventory->code,
        ]);
    }
}
