<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Jobs\DeliverOrderJob;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
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
    }

    public function test_webhook_can_arrive_before_order_is_created(): void
    {
        $publicId = '11111111-1111-1111-1111-111111111111';

        $payload = [
            'event_id' => 'evt_before_order',
            'order_id' => $publicId,
            'status' => PaymentStatus::PAID->value,
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

        $this->assertDatabaseHas('payment_events', [
            'event_id' => 'evt_before_order',
            'order_id' => null,
        ]);

        $this->assertDatabaseCount('payment_events', 1);

        $this->assertDatabaseMissing('orders', [
            'public_id' => $publicId,
        ]);
    }

    public function test_pending_webhook_is_applied_when_order_is_created(): void
    {
        $product = Product::factory()->create([
            'price' => 1290,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $publicId = (string) Str::uuid();

        $payload = [
            'event_id' => 'evt_pending_payment',
            'order_id' => $publicId,
            'status' => PaymentStatus::PAID->value,
            'amount' => 1290,
            'currency' => 'RUB',
            'created_at' => now()->toISOString(),
        ];

        $this->postSignedWebhook($payload)
            ->assertOk();

        $this->assertDatabaseHas('payment_events', [
            'event_id' => 'evt_pending_payment',
            'order_id' => null,
            'processed_at' => null,
        ]);

        $order = app(OrderService::class)->create(
            $product->sku,
            $publicId,
        );

        $this->assertSame(
            OrderStatus::PAID,
            $order->status,
        );

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PAID->value,
        ]);

        $this->assertDatabaseHas('payment_events', [
            'event_id' => 'evt_pending_payment',
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseMissing('payment_events', [
            'event_id' => 'evt_pending_payment',
            'processed_at' => null,
        ]);
    }

    public function test_newer_payment_event_wins_when_webhooks_arrive_out_of_order(): void
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

        $olderEventTime = now()->subMinute()->toISOString();
        $newerEventTime = now()->toISOString();

        $failedPayload = [
            'event_id' => 'evt_out_of_order_failed',
            'order_id' => $order->public_id,
            'status' => PaymentStatus::FAILED->value,
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => $olderEventTime,
        ];

        $paidPayload = [
            'event_id' => 'evt_out_of_order_paid',
            'order_id' => $order->public_id,
            'status' => PaymentStatus::PAID->value,
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => $newerEventTime,
        ];

        /*
         * The older FAILED event arrives first.
         */
        $this->postSignedWebhook($failedPayload)
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PAYMENT_FAILED->value,
        ]);

        /*
         * The newer PAID event arrives second.
         * Final state must correspond to the newer event,
         * regardless of arrival order.
         */
        $this->postSignedWebhook($paidPayload)
            ->assertOk();

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PAID->value,
        ]);

        $this->assertDatabaseCount('payment_events', 2);
    }

    public function test_same_event_id_cannot_be_used_for_another_order(): void
    {
        $product = Product::factory()->create();

        $firstOrder = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
            ]);

        $secondOrder = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
            ]);

        $firstPayload = [
            'event_id' => 'evt_reused',
            'order_id' => $firstOrder->public_id,
            'status' => PaymentStatus::PAID->value,
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => now()->toISOString(),
        ];

        $this->postSignedWebhook($firstPayload)
            ->assertOk();

        $secondPayload = [
            'event_id' => 'evt_reused',
            'order_id' => $secondOrder->public_id,
            'status' => PaymentStatus::PAID->value,
            'amount' => $product->price,
            'currency' => $product->currency,
            'created_at' => now()->toISOString(),
        ];

        $this->postSignedWebhook($secondPayload)
            ->assertStatus(422);

        $this->assertDatabaseHas('orders', [
            'id' => $firstOrder->id,
            'status' => OrderStatus::PAID->value,
        ]);

        $this->assertDatabaseHas('orders', [
            'id' => $secondOrder->id,
            'status' => OrderStatus::CREATED->value,
        ]);

        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_newer_paid_pending_event_wins_when_order_is_created(): void
    {
        $product = Product::factory()->create([
            'sku' => 'STEAM-TEST-500',
            'price' => 500,
            'currency' => 'USD',
            ]);
        $orderPublicId = (string)Str::uuid();
        $this->postSignedWebhook(['event_id' => 'evt-old-failed', 'order_id' => $orderPublicId, 'status' => 'failed', 'amount' => 500, 'currency' => 'USD', 'created_at' => '2026-01-01T10:00:00Z',])->assertOk();
        $this->postSignedWebhook(['event_id' => 'evt-new-paid', 'order_id' => $orderPublicId, 'status' => 'paid', 'amount' => 500, 'currency' => 'USD', 'created_at' => '2026-01-01T11:00:00Z',])->assertOk();
        $order = app(OrderService::class)->create($product->sku, $orderPublicId);
        $this->assertSame(OrderStatus::PAID, $order->status);
    }

    public function test_newer_failed_pending_event_wins_when_order_is_created(): void
    {
        $product = Product::factory()->create([
            'sku' => 'STEAM-TEST-500',
            'price' => 500,
            'currency' => 'USD',
        ]);

        $orderPublicId = (string) Str::uuid();

        $this->postSignedWebhook([
            'event_id' => 'evt-old-paid',
            'order_id' => $orderPublicId,
            'status' => 'paid',
            'amount' => 500,
            'currency' => 'USD',
            'created_at' => '2026-01-01T10:00:00Z',
        ])->assertOk();

        $this->postSignedWebhook([
            'event_id' => 'evt-new-failed',
            'order_id' => $orderPublicId,
            'status' => 'failed',
            'amount' => 500,
            'currency' => 'USD',
            'created_at' => '2026-01-01T11:00:00Z',
        ])->assertOk();

        $order = app(OrderService::class)->create(
            $product->sku,
            $orderPublicId,
        );

        $this->assertSame(OrderStatus::PAYMENT_FAILED, $order->status);
    }
}
