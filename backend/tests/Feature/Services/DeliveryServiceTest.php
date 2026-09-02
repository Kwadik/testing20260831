<?php

namespace Tests\Feature\Services;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Exceptions\IdempotencyKeyConflictException;
use App\Exceptions\OrderNotReadyException;
use App\Exceptions\OutOfStockException;
use App\Models\DeliveryAttempt;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\DeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_delivers_paid_order_and_creates_successful_attempt(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $inventoryItem = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestId = 'service-success-' . fake()->uuid();

        $result = app(DeliveryService::class)->deliver(
            $order,
            $requestId,
        );

        $this->assertSame(
            $inventoryItem->id,
            $result->id,
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $requestId,
            'provider' => 'inventory',
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $inventoryItem->id,
            'code' => $inventoryItem->code,
        ]);

        $attempt = DeliveryAttempt::query()
            ->where('request_id', $requestId)
            ->firstOrFail();

        $this->assertNotNull($attempt->started_at);
        $this->assertNotNull($attempt->finished_at);
        $this->assertNull($attempt->error);
    }

    public function test_it_returns_existing_successful_attempt_without_creating_another_attempt(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::DELIVERED,
            ]);

        $inventoryItem = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::DELIVERED,
            'order_id' => $order->id,
        ]);

        $requestId = 'service-existing-success-' . fake()->uuid();

        $attempt = DeliveryAttempt::factory()->create([
            'order_id' => $order->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::SUCCESS,
            'inventory_item_id' => $inventoryItem->id,
            'code' => $inventoryItem->code,
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
            'error' => null,
        ]);

        $result = app(DeliveryService::class)->deliver(
            $order,
            $requestId,
        );

        $this->assertSame(
            $inventoryItem->id,
            $result->id,
        );

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'id' => $attempt->id,
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $inventoryItem->id,
            'code' => $inventoryItem->code,
        ]);
    }

    public function test_it_throws_out_of_stock_exception_and_marks_order_and_attempt_as_failed(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $requestId = 'service-out-of-stock-' . fake()->uuid();

        $this->expectException(OutOfStockException::class);
        $this->expectExceptionMessage('Product is out of stock.');

        try {
            app(DeliveryService::class)->deliver(
                $order,
                $requestId,
            );
        } catch (OutOfStockException $e) {
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

            throw $e;
        }
    }

    public function test_it_throws_order_not_ready_exception_for_invalid_order_status(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
            ]);

        $requestId = 'service-order-not-ready-' . fake()->uuid();

        $this->expectException(OrderNotReadyException::class);
        $this->expectExceptionMessage('Order is not ready for delivery.');

        try {
            app(DeliveryService::class)->deliver(
                $order,
                $requestId,
            );
        } catch (OrderNotReadyException $e) {
            $this->assertDatabaseHas('orders', [
                'id' => $order->id,
                'status' => OrderStatus::CREATED->value,
            ]);

            $this->assertDatabaseHas('delivery_attempts', [
                'order_id' => $order->id,
                'request_id' => $requestId,
                'status' => DeliveryAttemptStatus::FAILED->value,
                'error' => 'Order is not ready for delivery.',
            ]);

            throw $e;
        }
    }

    public function test_it_throws_idempotency_conflict_when_request_id_belongs_to_another_order(): void
    {
        $product = Product::factory()->create();

        $firstOrder = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $secondOrder = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $requestId = 'service-idempotency-conflict-' . fake()->uuid();

        DeliveryAttempt::factory()->create([
            'order_id' => $firstOrder->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::SUCCESS,
        ]);

        $this->expectException(IdempotencyKeyConflictException::class);
        $this->expectExceptionMessage(
            'Idempotency-Key has already been used for another order.'
        );

        app(DeliveryService::class)->deliver(
            $secondOrder,
            $requestId,
        );
    }

    public function test_it_reuses_existing_processing_attempt_for_the_same_order(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $inventoryItem = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestId = 'service-processing-' . fake()->uuid();

        DeliveryAttempt::factory()->create([
            'order_id' => $order->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::PROCESSING,
        ]);

        $result = app(DeliveryService::class)->deliver(
            $order,
            $requestId,
        );

        $this->assertSame(
            $inventoryItem->id,
            $result->id,
        );

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $requestId,
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $inventoryItem->id,
            'code' => $inventoryItem->code,
        ]);
    }

    public function test_it_returns_already_delivered_inventory_for_new_idempotency_key(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::DELIVERED,
            ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::DELIVERED,
            'order_id' => $order->id,
        ]);

        $service = app(DeliveryService::class);

        $result = $service->deliver(
            $order,
            'new-idempotency-key',
        );

        $this->assertSame($item->id, $result->id);

        $this->assertDatabaseCount('inventory_items', 1);

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => 'new-idempotency-key',
            'status' => DeliveryAttemptStatus::SUCCESS,
            'inventory_item_id' => $item->id,
            'code' => $item->code,
        ]);
    }
}
