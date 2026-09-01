<?php

namespace Api;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Models\DeliveryAttempt;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class OrderDeliveryConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_same_idempotency_key_is_safe_under_concurrency(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestId = 'delivery-race-' . fake()->uuid();

        $url = "http://127.0.0.1:8000/api/orders/{$order->public_id}/deliver";

        $multiHandle = curl_multi_init();
        $handles = [];

        for ($i = 0; $i < 2; $i++) {
            $handle = curl_init($url);

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "Idempotency-Key: {$requestId}",
                ],
                CURLOPT_POSTFIELDS => '{}',
            ]);

            curl_multi_add_handle($multiHandle, $handle);
            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);

            if ($running) {
                curl_multi_select($multiHandle);
            }
        } while ($running && $status === CURLM_OK);

        $responses = [];

        foreach ($handles as $handle) {
            $raw = curl_multi_getcontent($handle);

            $responses[] = [
                'status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'errno' => curl_errno($handle),
                'error' => curl_error($handle),
                'content_type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE),
                'raw' => $raw,
            ];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        $this->assertCount(2, $responses);

        foreach ($responses as $response) {
            $this->assertSame(200, $response['status']);

            $body = json_decode(
                $response['raw'],
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            $this->assertSame('delivered', $body['status']);
            $this->assertSame($item->code, $body['code']);
        }

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $item->id,
            'code' => $item->code,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);
    }

    public function test_failed_attempt_is_safe_under_concurrent_retry(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $requestId = 'delivery-failed-race-' . fake()->uuid();

        DeliveryAttempt::factory()->create([
            'order_id' => $order->id,
            'provider' => 'inventory',
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::FAILED,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'error' => 'Product is out of stock.',
        ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $url = "http://127.0.0.1:8000/api/orders/{$order->public_id}/deliver";

        $multiHandle = curl_multi_init();
        $handles = [];

        for ($i = 0; $i < 2; $i++) {
            $handle = curl_init($url);

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "Idempotency-Key: {$requestId}",
                ],
                CURLOPT_POSTFIELDS => '{}',
            ]);

            curl_multi_add_handle($multiHandle, $handle);
            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);

            if ($running) {
                curl_multi_select($multiHandle);
            }
        } while ($running && $status === CURLM_OK);

        $responses = [];

        foreach ($handles as $handle) {
            $raw = curl_multi_getcontent($handle);

            $responses[] = [
                'status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'errno' => curl_errno($handle),
                'error' => curl_error($handle),
                'content_type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE),
                'raw' => $raw,
            ];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        $this->assertCount(2, $responses);

        foreach ($responses as $response) {
            $this->assertSame(200, $response['status']);

            $body = json_decode(
                $response['raw'],
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            $this->assertSame('delivered', $body['status']);
            $this->assertSame($item->code, $body['code']);
        }

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $item->id,
            'code' => $item->code,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);
    }

    public function test_different_idempotency_keys_are_safe_for_same_order_under_concurrency(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestIds = [
            'delivery-different-key-1-' . fake()->uuid(),
            'delivery-different-key-2-' . fake()->uuid(),
        ];

        $url = "http://127.0.0.1:8000/api/orders/{$order->public_id}/deliver";

        $multiHandle = curl_multi_init();

        $handles = [];

        foreach ($requestIds as $requestId) {
            $handle = curl_init($url);

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "Idempotency-Key: {$requestId}",
                ],
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_CONNECTTIMEOUT_MS => 2000,
                CURLOPT_TIMEOUT_MS => 15000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ]);

            curl_multi_add_handle($multiHandle, $handle);

            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);

            if ($running) {
                curl_multi_select($multiHandle);
            }
        } while ($running && $status === CURLM_OK);

        $responses = [];

        foreach ($handles as $handle) {
            $raw = curl_multi_getcontent($handle);

            $responses[] = [
                'status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'errno' => curl_errno($handle),
                'error' => curl_error($handle),
                'content_type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE),
                'body' => json_decode($raw, true, flags: JSON_THROW_ON_ERROR),
            ];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        $this->assertCount(2, $responses);

        foreach ($responses as $response) {
            $this->assertSame(200, $response['status']);
            $this->assertSame('delivered', $response['body']['status']);
            $this->assertSame($item->code, $response['body']['code']);
        }

        $this->assertDatabaseCount('delivery_attempts', 2);

        foreach ($requestIds as $requestId) {
            $this->assertDatabaseHas('delivery_attempts', [
                'order_id' => $order->id,
                'request_id' => $requestId,
                'status' => DeliveryAttemptStatus::SUCCESS->value,
                'inventory_item_id' => $item->id,
                'code' => $item->code,
            ]);
        }

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);
    }

    public function test_different_orders_are_safe_when_competing_for_last_inventory_item(): void
    {
        $product = Product::factory()->create();

        $orders = [
            Order::factory()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'amount' => $product->price,
                'currency' => $product->currency,
                'status' => OrderStatus::PAID,
            ]),
            Order::factory()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'amount' => $product->price,
                'currency' => $product->currency,
                'status' => OrderStatus::PAID,
            ]),
        ];

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestIds = [
            'delivery-last-item-1-' . fake()->uuid(),
            'delivery-last-item-2-' . fake()->uuid(),
        ];

        $urls = array_map(
            fn (Order $order): string =>
            "http://127.0.0.1:8000/api/orders/{$order->public_id}/deliver",
            $orders,
        );

        $multiHandle = curl_multi_init();

        $handles = [];

        foreach ($urls as $index => $url) {
            $handle = curl_init($url);

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "Idempotency-Key: {$requestIds[$index]}",
                ],
                CURLOPT_POSTFIELDS => '{}',
                CURLOPT_CONNECTTIMEOUT_MS => 2000,
                CURLOPT_TIMEOUT_MS => 15000,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            ]);

            curl_multi_add_handle($multiHandle, $handle);

            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);

            if ($running) {
                curl_multi_select($multiHandle);
            }
        } while ($running && $status === CURLM_OK);

        $responses = [];

        foreach ($handles as $handle) {
            $raw = curl_multi_getcontent($handle);

            $responses[] = [
                'status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'errno' => curl_errno($handle),
                'error' => curl_error($handle),
                'content_type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE),
                'body' => json_decode(
                    $raw,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                ),
            ];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        $this->assertCount(2, $responses);

        $successfulResponses = array_values(
            array_filter(
                $responses,
                fn (array $response): bool => $response['status'] === 200,
            ),
        );

        $failedResponses = array_values(
            array_filter(
                $responses,
                fn (array $response): bool => $response['status'] === 409,
            ),
        );

        $this->assertCount(1, $successfulResponses);
        $this->assertCount(1, $failedResponses);

        $this->assertSame(
            'delivered',
            $successfulResponses[0]['body']['status'],
        );

        $this->assertSame(
            $item->code,
            $successfulResponses[0]['body']['code'],
        );

        $this->assertSame(
            'Product is out of stock.',
            $failedResponses[0]['body']['message'],
        );

        $this->assertDatabaseCount('delivery_attempts', 2);

        $this->assertDatabaseCount('inventory_items', 1);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => InventoryStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseCount('orders', 2);

        $deliveredOrders = Order::query()
            ->whereIn('id', [$orders[0]->id, $orders[1]->id])
            ->where('status', OrderStatus::DELIVERED)
            ->get();

        $outOfStockOrders = Order::query()
            ->whereIn('id', [$orders[0]->id, $orders[1]->id])
            ->where('status', OrderStatus::OUT_OF_STOCK)
            ->get();

        $this->assertCount(1, $deliveredOrders);
        $this->assertCount(1, $outOfStockOrders);

        $this->assertSame(
            $deliveredOrders->first()->id,
            $item->fresh()->order_id,
        );

        $successAttempt = DeliveryAttempt::query()
            ->whereIn('request_id', $requestIds)
            ->where('status', DeliveryAttemptStatus::SUCCESS)
            ->firstOrFail();

        $failedAttempt = DeliveryAttempt::query()
            ->whereIn('request_id', $requestIds)
            ->where('status', DeliveryAttemptStatus::FAILED)
            ->firstOrFail();

        $this->assertSame($item->id, $successAttempt->inventory_item_id);
        $this->assertSame($item->code, $successAttempt->code);

        $this->assertSame(
            'Product is out of stock.',
            $failedAttempt->error,
        );
    }

    public function test_same_idempotency_key_cannot_be_used_for_different_orders_under_concurrency(): void
    {
        $product = Product::factory()->create();

        $orders = [];

        for ($i = 0; $i < 2; $i++) {
            $orders[] = Order::factory()->create([
                'product_id' => $product->id,
                'sku' => $product->sku,
                'amount' => $product->price,
                'currency' => $product->currency,
                'status' => OrderStatus::PAID,
            ]);
        }

        InventoryItem::factory()->count(2)->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestId = 'delivery-same-key-different-orders-' . fake()->uuid();

        $urls = [
            "http://127.0.0.1:8000/api/orders/{$orders[0]->public_id}/deliver",
            "http://127.0.0.1:8000/api/orders/{$orders[1]->public_id}/deliver",
        ];

        $multiHandle = curl_multi_init();
        $handles = [];

        foreach ($urls as $url) {
            $handle = curl_init($url);

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "Idempotency-Key: {$requestId}",
                ],
                CURLOPT_POSTFIELDS => '{}',
            ]);

            curl_multi_add_handle($multiHandle, $handle);
            $handles[] = $handle;
        }

        do {
            $status = curl_multi_exec($multiHandle, $running);

            if ($running) {
                curl_multi_select($multiHandle);
            }
        } while ($running && $status === CURLM_OK);

        $responses = [];

        foreach ($handles as $handle) {
            $raw = curl_multi_getcontent($handle);

            $responses[] = [
                'status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'errno' => curl_errno($handle),
                'error' => curl_error($handle),
                'content_type' => curl_getinfo($handle, CURLINFO_CONTENT_TYPE),
                'raw' => $raw,
            ];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        $this->assertCount(2, $responses);

        $successfulResponses = array_values(array_filter(
            $responses,
            fn (array $response): bool => $response['status'] === 200,
        ));

        $conflictResponses = array_values(array_filter(
            $responses,
            fn (array $response): bool => $response['status'] === 409,
        ));

        $this->assertCount(1, $successfulResponses);
        $this->assertCount(1, $conflictResponses);

        $successBody = json_decode(
            $successfulResponses[0]['raw'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $conflictBody = json_decode(
            $conflictResponses[0]['raw'],
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('delivered', $successBody['status']);

        $this->assertSame(
            'Idempotency-Key has already been used for another order.',
            $conflictBody['message'],
        );

        $this->assertDatabaseCount('delivery_attempts', 1);

        $attempt = DeliveryAttempt::query()
            ->where('request_id', $requestId)
            ->firstOrFail();

        $this->assertSame(
            DeliveryAttemptStatus::SUCCESS,
            $attempt->status,
        );

        $this->assertContains(
            $attempt->order_id,
            [$orders[0]->id, $orders[1]->id],
        );

        $this->assertNotNull($attempt->inventory_item_id);
        $this->assertNotNull($attempt->code);

        $deliveredOrders = Order::query()
            ->whereIn('id', [$orders[0]->id, $orders[1]->id])
            ->where('status', OrderStatus::DELIVERED)
            ->get();

        $paidOrders = Order::query()
            ->whereIn('id', [$orders[0]->id, $orders[1]->id])
            ->where('status', OrderStatus::PAID)
            ->get();

        $this->assertCount(1, $deliveredOrders);
        $this->assertCount(1, $paidOrders);

        $this->assertDatabaseCount('inventory_items', 2);

        $deliveredItem = InventoryItem::query()
            ->where('status', InventoryStatus::DELIVERED)
            ->firstOrFail();

        $this->assertSame(
            $deliveredOrders->first()->id,
            $deliveredItem->order_id,
        );
    }
}
