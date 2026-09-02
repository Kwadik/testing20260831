<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Models\DeliveryAttempt;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class OrderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_order_receives_available_inventory_item(): void
    {
        $product = Product::factory()->create([
            'sku' => 'KEY-CS2-PRIME',
            'price' => 1290,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $inventory = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $response = $this->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
            [
                'Idempotency-Key' => 'delivery-paid-order-001',
            ],
        );

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $inventory->code,
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'provider' => 'inventory',
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $inventory->id,
            'code' => $inventory->code,
        ]);
    }

    public function test_paid_order_becomes_out_of_stock_when_inventory_is_empty(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Product is out of stock.');

        app(DeliveryService::class)->deliver(
            $order,
            'delivery-out-of-stock-test',
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::OUT_OF_STOCK->value,
        ]);
    }

    public function test_delivered_order_does_not_receive_another_inventory_item(): void
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

        app(DeliveryService::class)->deliver(
            $order,
            'delivery-out-of-stock-test',
        );

        $secondItem = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $result = app(DeliveryService::class)->deliver(
            $order->fresh(),
            'delivery-second-test',
        );

        $this->assertSame($item->id, $result->id);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $secondItem->id,
            'status' => InventoryStatus::AVAILABLE->value,
            'order_id' => null,
        ]);

        $this->assertDatabaseCount('inventory_items', 2);
    }

    public function test_paid_order_can_be_delivered_through_api(): void
    {
        $product = Product::factory()->create([
            'sku' => 'KEY-CS2-PRIME',
            'price' => 1290,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $inventory = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $response = $this->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
            [
                'Idempotency-Key' => 'delivery-paid-order-002',
            ],
        );

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $inventory->code,
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'provider' => 'inventory',
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $inventory->id,
            'code' => $inventory->code,
        ]);
    }

    public function test_delivery_is_idempotent_for_same_request_id(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $firstItem = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $secondItem = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $this->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
            [
                'Idempotency-Key' => 'delivery-paid-order-001',
            ],
        )
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $firstItem->code,
            ]);

        $this->postJson("/api/orders/{$order->public_id}/deliver",
            [],
            [
                'Idempotency-Key' => 'delivery-paid-order-001',
            ],
        )
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $firstItem->code,
            ]);

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $firstItem->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $secondItem->id,
            'status' => InventoryStatus::AVAILABLE->value,
            'order_id' => null,
        ]);
    }

    public function test_delivery_requires_idempotency_key(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $this->postJson(
            "/api/orders/{$order->public_id}/deliver"
        )->assertStatus(422);
    }

    public function test_out_of_stock_creates_failed_delivery_attempt(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $requestId = 'delivery-out-of-stock-001';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Product is out of stock.');

        try {
            app(DeliveryService::class)->deliver($order, $requestId);
        } finally {
            $this->assertDatabaseHas('delivery_attempts', [
                'order_id' => $order->id,
                'provider' => 'inventory',
                'request_id' => $requestId,
                'status' => DeliveryAttemptStatus::FAILED->value,
                'error' => 'Product is out of stock.',
            ]);
        }
    }

    public function test_unpaid_order_creates_failed_delivery_attempt(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::CREATED,
        ]);

        $requestId = 'delivery-unpaid-001';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Order is not ready for delivery.');

        try {
            app(DeliveryService::class)->deliver($order, $requestId);
        } finally {
            $this->assertDatabaseHas('delivery_attempts', [
                'order_id' => $order->id,
                'provider' => 'inventory',
                'request_id' => $requestId,
                'status' => DeliveryAttemptStatus::FAILED->value,
                'error' => 'Order is not ready for delivery.',
            ]);
        }
    }

    public function test_same_idempotency_key_cannot_be_used_for_another_order(): void
    {
        $product = Product::factory()->create();

        $firstOrder = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $secondOrder = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $inventory = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestId = 'delivery-reused-key-001';

        $this->postJson(
            "/api/orders/{$firstOrder->public_id}/deliver",
            [],
            ['Idempotency-Key' => $requestId],
        )->assertOk();

        $response = $this->postJson(
            "/api/orders/{$secondOrder->public_id}/deliver",
            [],
            ['Idempotency-Key' => $requestId],
        );

        $response->assertStatus(409);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventory->id,
            'order_id' => $firstOrder->id,
            'status' => InventoryStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $secondOrder->id,
            'status' => OrderStatus::PAID->value,
        ]);
    }

    public function test_only_one_order_can_receive_the_same_inventory_item(): void
    {
        $product = Product::factory()->create();

        $firstOrder = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $secondOrder = Order::factory()->create([
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

        $firstResult = app(DeliveryService::class)->deliver(
            $firstOrder,
            'concurrent-delivery-001',
        );

        $this->assertSame($item->id, $firstResult->id);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Product is out of stock.');

        app(DeliveryService::class)->deliver(
            $secondOrder,
            'concurrent-delivery-002',
        );

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $firstOrder->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $firstOrder->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $secondOrder->id,
            'status' => OrderStatus::OUT_OF_STOCK->value,
        ]);
    }

    public function test_same_idempotency_key_cannot_create_two_delivery_attempts(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
        ]);

        $requestId = 'delivery-concurrent-test';

        $this->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
            ['Idempotency-Key' => $requestId],
        )->assertOk();

        $this->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
            ['Idempotency-Key' => $requestId],
        )->assertOk();

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::SUCCESS->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseCount('inventory_items', 1);
    }

    public function test_processing_attempt_can_be_completed(): void
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

        $requestId = 'delivery-processing-001';

        $attempt = DeliveryAttempt::factory()->create([
            'order_id' => $order->id,
            'provider' => 'inventory',
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::PROCESSING,
            'inventory_item_id' => null,
            'code' => null,
            'started_at' => now(),
            'finished_at' => null,
        ]);

        $result = app(DeliveryService::class)->deliver(
            $order->fresh(),
            $requestId,
        );

        $this->assertSame($item->id, $result->id);

        $this->assertDatabaseHas('delivery_attempts', [
            'id' => $attempt->id,
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

        $this->assertDatabaseCount('delivery_attempts', 1);
    }

    public function test_failed_delivery_can_be_retried_with_same_request_id(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $requestId = 'delivery-retry-after-failure-001';

        // Первая попытка: товара нет.
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Product is out of stock.');

        try {
            app(DeliveryService::class)->deliver($order, $requestId);
        } finally {
            $this->assertDatabaseHas('delivery_attempts', [
                'order_id' => $order->id,
                'request_id' => $requestId,
                'status' => DeliveryAttemptStatus::FAILED->value,
            ]);
        }

        // Появился товар.
        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        // Вторая попытка использует тот же Idempotency-Key.
        $result = app(DeliveryService::class)->deliver(
            $order->fresh(),
            $requestId,
        );

        $this->assertSame($item->id, $result->id);

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

    public function test_timed_out_delivery_can_be_retried_with_same_request_id(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $requestId = 'delivery-timeout-retry-001';

        $attempt = DeliveryAttempt::factory()->create([
            'order_id' => $order->id,
            'request_id' => $requestId,
            'provider' => 'inventory',
            'status' => DeliveryAttemptStatus::TIMEOUT,
        ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $result = app(DeliveryService::class)->deliver(
            $order->fresh(),
            $requestId,
        );

        $this->assertSame($item->id, $result->id);

        $this->assertDatabaseHas('delivery_attempts', [
            'id' => $attempt->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $item->id,
            'code' => $item->code,
        ]);

        $this->assertDatabaseCount('delivery_attempts', 1);
    }

    public function test_failed_delivery_retry_without_inventory_stays_failed(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $requestId = 'delivery-failed-retry-001';

        try {
            app(DeliveryService::class)->deliver($order, $requestId);

            $this->fail('Expected first delivery attempt to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Product is out of stock.', $e->getMessage());
        }

        try {
            app(DeliveryService::class)->deliver(
                $order->fresh(),
                $requestId,
            );

            $this->fail('Expected retry to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Order is not ready for delivery.', $e->getMessage());
        }

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::FAILED->value,
            'error' => 'Order is not ready for delivery.',
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::OUT_OF_STOCK->value,
        ]);
    }

    public function test_failed_delivery_can_be_retried_after_inventory_becomes_available(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $requestId = 'delivery-failed-retry-001';

        try {
            app(DeliveryService::class)->deliver($order, $requestId);

            $this->fail('Expected first delivery attempt to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Product is out of stock.', $e->getMessage());
        }

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::OUT_OF_STOCK->value,
        ]);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::FAILED->value,
            'error' => 'Product is out of stock.',
        ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $order->refresh();

        $order->update([
            'status' => OrderStatus::PAID,
        ]);
//
//        dump([
//            'order_model_after_update' => $order->status?->value,
//            'order_db_after_update' => Order::query()
//                ->whereKey($order->id)
//                ->value('status'),
//        ]);

        $result = app(DeliveryService::class)->deliver(
            $order->fresh(),
            $requestId,
        );

        $this->assertSame($item->id, $result->id);

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'id' => DeliveryAttempt::query()
                ->where('request_id', $requestId)
                ->value('id'),
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

    public function test_successful_retry_remains_idempotent(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::PAID,
        ]);

        $requestId = 'delivery-failed-then-success-001';

        try {
            app(DeliveryService::class)->deliver($order, $requestId);

            $this->fail('Expected first delivery attempt to fail.');
        } catch (RuntimeException $e) {
            $this->assertSame('Product is out of stock.', $e->getMessage());
        }

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::OUT_OF_STOCK->value,
        ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $order->refresh();

        $order->update([
            'status' => OrderStatus::PAID,
        ]);

        $firstResult = app(DeliveryService::class)->deliver(
            $order->fresh(),
            $requestId,
        );

        $secondResult = app(DeliveryService::class)->deliver(
            $order->fresh(),
            $requestId,
        );

        $this->assertSame($item->id, $firstResult->id);
        $this->assertSame($item->id, $secondResult->id);

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

        $this->assertDatabaseCount('inventory_items', 1);
    }
}
