<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Models\DeliveryAttempt;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OrderDeliveryControllerTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('orderStatusesThatCannotBeDelivered')]
    public function test_delivery_is_rejected_when_order_is_not_ready(
        OrderStatus $status,
    ): void {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => $status,
            ]);

        $response = $this->withHeader(
            'Idempotency-Key',
            'delivery-invalid-status-' . fake()->uuid(),
        )->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Order is not ready for delivery.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => $status->value,
        ]);

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'status' => DeliveryAttemptStatus::FAILED->value,
            'error' => 'Order is not ready for delivery.',
        ]);

    }

    public static function orderStatusesThatCannotBeDelivered(): array
    {
        return [
            'created' => [OrderStatus::CREATED],
            'delivering' => [OrderStatus::DELIVERING],
            'payment failed' => [OrderStatus::PAYMENT_FAILED],
            'out of stock' => [OrderStatus::OUT_OF_STOCK],
            'delivery failed' => [OrderStatus::DELIVERY_FAILED],
        ];
    }

    public function test_idempotency_key_is_required(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $response = $this->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        );

        $response
            ->assertStatus(422)
            ->assertJson([
                'message' => 'Idempotency-Key header is required.',
            ]);
    }

    public function test_unknown_order_returns_404(): void
    {
        $response = $this->withHeader(
            'Idempotency-Key',
            'delivery-unknown-order-' . fake()->uuid(),
        )->postJson(
            '/api/orders/00000000-0000-0000-0000-000000000000/deliver',
            [],
        );

        $response->assertNotFound();
    }

    public function test_successful_delivery_returns_delivered_status_and_code(): void
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

        $requestId = 'delivery-success-' . fake()->uuid();

        $response = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        );

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $inventoryItem->code,
            ]);

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
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $inventoryItem->id,
            'code' => $inventoryItem->code,
        ]);
    }

    public function test_successful_delivery_can_be_repeated_with_same_idempotency_key(): void
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

        $requestId = 'delivery-repeat-' . fake()->uuid();

        $firstResponse = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        );

        $firstResponse
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $inventoryItem->code,
            ]);

        $secondResponse = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        );

        $secondResponse
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $inventoryItem->code,
            ]);

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseCount('inventory_items', 1);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);
    }

    public function test_delivery_returns_conflict_when_product_is_out_of_stock(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $requestId = 'delivery-out-of-stock-' . fake()->uuid();

        $response = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        );

        $response
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Product is out of stock.',
            ]);

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

        $this->assertDatabaseCount('inventory_items', 0);
    }

    public function test_same_idempotency_key_for_another_order_returns_conflict(): void
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

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestId = 'delivery-conflict-' . fake()->uuid();

        $firstResponse = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$firstOrder->public_id}/deliver",
            [],
        );

        $firstResponse->assertOk();

        $secondResponse = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$secondOrder->public_id}/deliver",
            [],
        );

        $secondResponse
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Idempotency-Key has already been used for another order.',
            ]);

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('orders', [
            'id' => $firstOrder->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $secondOrder->id,
            'status' => OrderStatus::PAID->value,
        ]);
    }

    public function test_delivery_cannot_be_retried_after_out_of_stock_with_new_idempotency_key(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $firstRequestId = 'delivery-out-of-stock-first-' . fake()->uuid();

        $firstResponse = $this->withHeader(
            'Idempotency-Key',
            $firstRequestId,
        )->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        );

        $firstResponse
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Product is out of stock.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::OUT_OF_STOCK->value,
        ]);

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $secondRequestId = 'delivery-out-of-stock-retry-' . fake()->uuid();

        $secondResponse = $this->withHeader(
            'Idempotency-Key',
            $secondRequestId,
        )->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        );

        $secondResponse
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Order is not ready for delivery.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::OUT_OF_STOCK->value,
        ]);

        $this->assertDatabaseCount('delivery_attempts', 2);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $firstRequestId,
            'status' => DeliveryAttemptStatus::FAILED->value,
            'error' => 'Product is out of stock.',
        ]);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => $secondRequestId,
            'status' => DeliveryAttemptStatus::FAILED->value,
            'error' => 'Order is not ready for delivery.',
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE->value,
            'order_id' => null,
        ]);
    }

    public function test_out_of_stock_order_can_be_retried_after_inventory_is_restocked(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::PAID,
            ]);

        $firstRequestId = 'delivery-out-of-stock-first-' . fake()->uuid();

        $this->withHeader(
            'Idempotency-Key',
            $firstRequestId,
        )->postJson(
            "/api/orders/{$order->public_id}/deliver",
            [],
        )
            ->assertStatus(409)
            ->assertJson([
                'message' => 'Product is out of stock.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::OUT_OF_STOCK->value,
        ]);

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $retryRequestId = 'delivery-retry-' . fake()->uuid();

        $response = $this->withHeader(
            'Idempotency-Key',
            $retryRequestId,
        )->postJson(
            "/api/orders/{$order->public_id}/retry-delivery",
            [],
        );

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseCount('inventory_items', 1);

        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $product->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);
    }

    public function test_delivery_failed_order_can_be_retried(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::DELIVERY_FAILED,
            ]);

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestId = 'delivery-failed-retry-' . fake()->uuid();

        $response = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$order->public_id}/retry-delivery",
            [],
        );

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $product->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);
    }

    public function test_delivery_retry_is_idempotent(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::OUT_OF_STOCK,
            ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $requestId = 'delivery-retry-idempotent-' . fake()->uuid();

        $firstResponse = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$order->public_id}/retry-delivery",
            [],
        );

        $firstResponse
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $item->code,
            ]);

        $secondResponse = $this->withHeader(
            'Idempotency-Key',
            $requestId,
        )->postJson(
            "/api/orders/{$order->public_id}/retry-delivery",
            [],
        );

        $secondResponse
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $item->code,
            ]);

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseCount('inventory_items', 1);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);
    }

    public function test_delivery_retry_after_delivery_does_not_create_another_attempt(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::OUT_OF_STOCK,
            ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $firstRequestId = 'delivery-retry-first-' . fake()->uuid();

        $this->withHeader(
            'Idempotency-Key',
            $firstRequestId,
        )->postJson(
            "/api/orders/{$order->public_id}/retry-delivery",
            [],
        )
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $item->code,
            ]);

        $secondRequestId = 'delivery-retry-after-delivery-' . fake()->uuid();

        $this->withHeader(
            'Idempotency-Key',
            $secondRequestId,
        )->postJson(
            "/api/orders/{$order->public_id}/retry-delivery",
            [],
        )
            ->assertOk()
            ->assertJson([
                'status' => 'delivered',
                'code' => $item->code,
            ]);

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseCount('inventory_items', 1);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);
    }
}
