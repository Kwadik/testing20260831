<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
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
            "/api/orders/{$order->public_id}/deliver"
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

        app(DeliveryService::class)->deliver($order);

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

        app(DeliveryService::class)->deliver($order);

        $secondItem = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $result = app(DeliveryService::class)->deliver($order->fresh());

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
            "/api/orders/{$order->public_id}/deliver"
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
}
