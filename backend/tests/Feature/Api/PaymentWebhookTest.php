<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_webhook_marks_order_as_paid(): void
    {
        $product = Product::factory()->create([
            'price' => 1290,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => 'created',
            ]);

        $response = $this->postJson('/api/payment/webhook', [
            'event_id' => 'evt_test_001',
            'order_id' => $order->public_id,
            'status' => 'paid',
            'amount' => 1290,
            'currency' => 'RUB',
            'created_at' => now()->toISOString(),
        ]);

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'accepted',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);

        $this->assertDatabaseHas('payment_events', [
            'event_id' => 'evt_test_001',
            'order_id' => $order->id,
        ]);
    }

    public function test_duplicate_webhook_does_not_change_order_again(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => 'created',
            ]);

        $payload = [
            'event_id' => 'evt_duplicate',
            'order_id' => $order->public_id,
            'status' => 'paid',
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => now()->toISOString(),
        ];

        $this->postJson('/api/payment/webhook', $payload)
            ->assertOk();

        $this->postJson('/api/payment/webhook', $payload)
            ->assertOk();

        $this->assertDatabaseCount('payment_events', 1);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'paid',
        ]);
    }

    public function test_failed_webhook_marks_order_as_payment_failed(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => 'created',
            ]);

        $this->postJson('/api/payment/webhook', [
            'event_id' => 'evt_failed',
            'order_id' => $order->public_id,
            'status' => 'failed',
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => now()->toISOString(),
        ])->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_failed',
        ]);
    }
}
