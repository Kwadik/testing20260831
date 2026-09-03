<?php

namespace Tests\Feature\Api;

use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Enums\DeliveryAttemptStatus;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PaymentWebhookQueueTest extends TestCase
{
    use DatabaseMigrations;

    private function postSignedWebhook(array $payload)
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        return $this->call(
            'POST',
            '/api/payment/webhook',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X-Signature' => hash_hmac(
                    'sha256',
                    $body,
                    config('services.payment.webhook_secret'),
                ),
            ],
            $body,
        );
    }

    public function test_paid_webhook_delivers_order_through_queue(): void
    {
        $product = Product::factory()->create([
            'price' => 1290,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
            ]);

        $inventoryItem = InventoryItem::factory()
            ->create([
                'product_id' => $product->id,
                'status' => InventoryStatus::AVAILABLE,
                'order_id' => null,
                'code' => 'E2E-TEST-KEY-001',
            ]);

        $payload = [
            'event_id' => 'evt_queue_e2e_001',
            'order_id' => $order->public_id,
            'status' => 'paid',
            'amount' => 1290,
            'currency' => 'RUB',
            'created_at' => now()->toISOString(),
        ];

        $this->postSignedWebhook($payload)
            ->assertOk()
            ->assertJson([
                'status' => 'accepted',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PAID->value,
        ]);

        $this->assertDatabaseHas('payment_events', [
            'event_id' => 'evt_queue_e2e_001',
            'order_id' => $order->id,
        ]);

        $this->waitFor(function () use ($order): bool {
            return Order::query()
                ->whereKey($order->id)
                ->where('status', OrderStatus::DELIVERED)
                ->exists();
        });

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::DELIVERED->value,
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $inventoryItem->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
            'code' => 'E2E-TEST-KEY-001',
        ]);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'request_id' => 'delivery-' . $order->public_id,
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $inventoryItem->id,
            'code' => 'E2E-TEST-KEY-001',
        ]);
    }

    private function waitFor(
        callable $condition,
        int $timeoutSeconds = 10,
        int $intervalMilliseconds = 100,
    ): void {
        $deadline = microtime(true) + $timeoutSeconds;

        do {
            if ($condition()) {
                return;
            }

            usleep($intervalMilliseconds * 1000);
        } while (microtime(true) < $deadline);

        $this->fail(
            "Condition was not satisfied within {$timeoutSeconds} seconds."
        );
    }
}
