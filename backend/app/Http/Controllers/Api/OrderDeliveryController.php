<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\OrderNotReadyException;
use App\Exceptions\OutOfStockException;
use App\Http\Controllers\Controller;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Services\DeliveryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
                allowDelivering: false,
            );
        } catch (
        IdempotencyKeyConflictException
        | OutOfStockException
        | OrderNotReadyException $e
        ) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'status' => 'delivered',
            'code' => $inventory->code,
        ]);
    }

    public function retryDelivery(Request $request, string $publicId): JsonResponse
    {
        $order = Order::where('public_id', $publicId)->firstOrFail();

        $requestId = $request->header('Idempotency-Key');

        if (! $requestId) {
            return response()->json([
                'message' => 'Idempotency-Key header is required.',
            ], 422);
        }

        $deliveredInventory = DB::transaction(function () use ($order): ?InventoryItem {
            $lockedOrder = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($lockedOrder->status, [
                OrderStatus::OUT_OF_STOCK,
                OrderStatus::DELIVERY_FAILED,
            ], true)) {
                $lockedOrder->update([
                    'status' => OrderStatus::PAID,
                ]);

                return null;
            }

            if ($lockedOrder->status === OrderStatus::DELIVERED) {
                return $lockedOrder->inventoryItem()->firstOrFail();
            }

            throw new OrderNotReadyException(
                'Order is not in a recoverable delivery state.'
            );
        });

        if ($deliveredInventory !== null) {
            return response()->json([
                'status' => 'delivered',
                'code' => $deliveredInventory->code,
            ]);
        }

        try {
            $inventory = $this->deliveryService->deliver(
                $order->fresh(),
                $requestId,
                allowDelivering: false,
            );
        } catch (
        IdempotencyKeyConflictException
        | OutOfStockException
        | OrderNotReadyException $e
        ) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 409);
        }

        return response()->json([
            'status' => 'delivered',
            'code' => $inventory->code,
        ]);
    }

    public function paidNotDelivered(): JsonResponse
    {
        $orders = Order::query()
            ->whereIn('status', [
                OrderStatus::OUT_OF_STOCK,
                OrderStatus::DELIVERY_FAILED,
            ])
            ->orderBy('id')
            ->get([
                'public_id',
                'sku',
                'amount',
                'currency',
                'status',
                'created_at',
            ]);

        return response()->json([
            'data' => $orders->map(static function (Order $order): array {
                return [
                    'id' => $order->public_id,
                    'sku' => $order->sku,
                    'amount' => $order->amount,
                    'currency' => $order->currency,
                    'status' => $order->status->value,
                    'created_at' => $order->created_at?->toISOString(),
                ];
            })->values(),
        ]);
    }
}
