<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

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

        $payload = [
            'event_id' => 'evt_test_001',
            'order_id' => $order->public_id,
            'status' => 'paid',
            'amount' => 1290,
            'currency' => 'RUB',
            'created_at' => now()->toISOString(),
        ];
        $response = $this->postSignedWebhook($payload);

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

        $this->postSignedWebhook($payload)->assertOk();

        $this->postSignedWebhook($payload)->assertOk();

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

        $payload = [
            'event_id' => 'evt_failed',
            'order_id' => $order->public_id,
            'status' => 'failed',
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => now()->toISOString(),
        ];
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => 'payment_failed',
        ]);
    }

    public function test_paid_webhook_with_wrong_amount_does_not_pay_order(): void
    {
        $product = Product::factory()->create([
            'price' => 1290,
            'currency' => 'RUB',
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
            ]);

        $payload = [
            'event_id' => 'evt_wrong_amount',
            'order_id' => $order->public_id,
            'status' => PaymentStatus::PAID->value,
            'amount' => 1,
            'currency' => 'RUB',
            'created_at' => now()->toISOString(),
        ];
        $response = $this->postSignedWebhook($payload);

        $response->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CREATED->value,
        ]);

        $this->assertDatabaseMissing('payment_events', [
            'event_id' => 'evt_wrong_amount',
        ]);
    }

    public function test_paid_webhook_with_wrong_currency_does_not_pay_order(): void
    {
        $product = Product::factory()->create([
            'price' => 1290,
            'currency' => 'RUB',
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
            ]);

        $payload = [
            'event_id' => 'evt_wrong_currency',
            'order_id' => $order->public_id,
            'status' => PaymentStatus::PAID->value,
            'amount' => 1290,
            'currency' => 'USD',
            'created_at' => now()->toISOString(),
        ];
        $response = $this->postSignedWebhook($payload);

        $response->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CREATED->value,
        ]);

        $this->assertDatabaseMissing('payment_events', [
            'event_id' => 'evt_wrong_currency',
        ]);
    }

    public function test_payment_webhook_rejects_invalid_signature(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
            ]);

        $payload = [
            'event_id' => 'evt_invalid_signature',
            'order_id' => $order->public_id,
            'status' => PaymentStatus::PAID->value,
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => now()->toISOString(),
        ];

        $response = $this->postJson(
            '/api/payment/webhook',
            $payload,
            [
                'X-Signature' => 'invalid-signature',
            ],
        );

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid webhook signature.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CREATED->value,
        ]);

        $this->assertDatabaseMissing('payment_events', [
            'event_id' => 'evt_invalid_signature',
        ]);
    }

    public function test_payment_webhook_rejects_missing_signature(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
            ]);

        $payload = [
            'event_id' => 'evt_missing_signature',
            'order_id' => $order->public_id,
            'status' => PaymentStatus::PAID->value,
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => now()->toISOString(),
        ];

        $response = $this->postJson(
            '/api/payment/webhook',
            $payload,
        );

        $response
            ->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid webhook signature.',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::CREATED->value,
        ]);

        $this->assertDatabaseMissing('payment_events', [
            'event_id' => 'evt_missing_signature',
        ]);
    }}
