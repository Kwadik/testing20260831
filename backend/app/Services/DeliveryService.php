<?php

namespace App\Services;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Models\DeliveryAttempt;
use App\Models\InventoryItem;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DeliveryService
{
    public function deliver(Order $order): InventoryItem
    {
        return DB::transaction(function () use ($order): InventoryItem {
            $order = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($order->status === OrderStatus::DELIVERED) {
                return $order->inventoryItem()->firstOrFail();
            }

            if ($order->status !== OrderStatus::PAID) {
                throw new RuntimeException('Order is not ready for delivery.');
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

                throw new RuntimeException('Product is out of stock.');
            }

            $attempt = DeliveryAttempt::create([
                'order_id' => $order->id,
                'provider' => 'inventory',
                'request_id' => (string) str()->uuid(),
                'status' => DeliveryAttemptStatus::PROCESSING,
                'inventory_item_id' => $item->id,
                'started_at' => now(),
            ]);

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

            $attempt->update([
                'status' => DeliveryAttemptStatus::SUCCESS,
                'code' => $item->code,
                'finished_at' => now(),
            ]);

            return $item->fresh();
        });
    }
}
