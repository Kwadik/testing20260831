<?php

namespace App\Services;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Models\DeliveryAttempt;
use App\Models\InventoryItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliveryService
{
    public function deliver(Order $order, string $requestId): InventoryItem
    {
        $attempt = DeliveryAttempt::where('request_id', $requestId)->first();

        if ($attempt && $attempt->order_id !== $order->id) {
            throw new IdempotencyKeyConflictException(
                'Idempotency-Key has already been used for another order.'
            );
        }

        $attempt ??= DeliveryAttempt::create([
            'order_id' => $order->id,
            'provider' => 'inventory',
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::PROCESSING,
            'started_at' => now(),
        ]);

        if ($attempt->status === DeliveryAttemptStatus::SUCCESS) {
            return $attempt->inventoryItem()->firstOrFail();
        }

        try {
            $item = DB::transaction(function () use ($order): InventoryItem {
                $order = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($order->status === OrderStatus::DELIVERED) {
                    return $order->inventoryItem()->firstOrFail();
                }

                if ($order->status !== OrderStatus::PAID) {
                    throw new RuntimeException(
                        'Order is not ready for delivery.'
                    );
                }

                $item = InventoryItem::query()
                    ->where('product_id', $order->product_id)
                    ->where('status', InventoryStatus::AVAILABLE)
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    $order->update([
                        'status' => OrderStatus::OUT_OF_STOCK,
                    ]);

                    throw new RuntimeException(
                        'Product is out of stock.'
                    );
                }

                $order->update([
                    'status' => OrderStatus::DELIVERING,
                ]);

                $item->update([
                    'status' => InventoryStatus::RESERVED,
                    'order_id' => $order->id,
                ]);

                $item->update([
                    'status' => InventoryStatus::DELIVERED,
                ]);

                $order->update([
                    'status' => OrderStatus::DELIVERED,
                ]);

                return $item->fresh();
            });

            $attempt->update([
                'status' => DeliveryAttemptStatus::SUCCESS,
                'inventory_item_id' => $item->id,
                'code' => $item->code,
                'finished_at' => now(),
                'error' => null,
            ]);

            return $item;
        } catch (\Throwable $e) {
            $attempt->update([
                'status' => DeliveryAttemptStatus::FAILED,
                'finished_at' => now(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
