<?php

namespace App\Services;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\OutOfStockException;
use App\Models\DeliveryAttempt;
use App\Models\InventoryItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliveryService
{
    public function deliver(Order $order, string $requestId): InventoryItem
    {
        $attempt = $this->getOrCreateAttempt($order, $requestId);

        if ($attempt->status === DeliveryAttemptStatus::SUCCESS) {
            return $attempt->inventoryItem()->firstOrFail();
        }

        try {
            $item = DB::transaction(function () use ($order): InventoryItem {
                $order = Order::query()
                    ->whereKey($order->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                /*
                 * Test-only delay.
                 *
                 * It happens while the order row is locked, so a concurrent
                 * delivery request has to wait for this transaction to finish.
                 */
                if (app()->environment('testing')) {
                    $delayMs = (int) env(
                        'DELIVERY_CONCURRENCY_TEST_DELAY_MS',
                        0
                    );

                    if ($delayMs > 0) {
                        usleep($delayMs * 1000);
                    }
                }

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
                    throw new OutOfStockException(
                        'Product is out of stock.'
                    );
                }

                $order->update([
                    'status' => OrderStatus::DELIVERING,
                ]);

                $item->update([
                    'status' => InventoryStatus::DELIVERED,
                    'order_id' => $order->id,
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
            if ($e instanceof OutOfStockException) {
                $order->update([
                    'status' => OrderStatus::OUT_OF_STOCK,
                ]);
            }

            $attempt->update([
                'status' => DeliveryAttemptStatus::FAILED,
                'finished_at' => now(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function getOrCreateAttempt(
        Order $order,
        string $requestId
    ): DeliveryAttempt {
        $attempt = DeliveryAttempt::where('request_id', $requestId)->first();

        if ($attempt) {
            if ($attempt->order_id !== $order->id) {
                throw new IdempotencyKeyConflictException(
                    'Idempotency-Key has already been used for another order.'
                );
            }

            return $attempt;
        }

        try {
            return DeliveryAttempt::create([
                'order_id' => $order->id,
                'provider' => 'inventory',
                'request_id' => $requestId,
                'status' => DeliveryAttemptStatus::PROCESSING,
                'started_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() !== '23505') {
                throw $e;
            }

            $attempt = DeliveryAttempt::where('request_id', $requestId)
                ->firstOrFail();

            if ($attempt->order_id !== $order->id) {
                throw new IdempotencyKeyConflictException(
                    'Idempotency-Key has already been used for another order.'
                );
            }

            return $attempt;
        }
    }
}
