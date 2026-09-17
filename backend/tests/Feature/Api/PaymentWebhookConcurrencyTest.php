<?php

namespace Tests\Feature\Api;

use App\Enums\DeliveryAttemptStatus;
use App\Enums\InventoryStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Tests\TestCase;

class PaymentWebhookConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_same_pending_webhook_is_idempotent_under_concurrency(): void
    {
        $publicId = '11111111-1111-1111-1111-111111111111';

        $payload = [
            'event_id' => 'evt_pending_race_001',
            'order_id' => $publicId,
            'status' => PaymentStatus::PAID->value,
            'amount' => 1290,
            'currency' => 'RUB',
            'created_at' => now()->toISOString(),
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);

        $signature = hash_hmac(
            'sha256',
            $body,
            config('services.payment.webhook_secret'),
        );

        $url = 'http://127.0.0.1:8000/api/payment/webhook';

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
                    "X-Signature: {$signature}",
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_CONNECTTIMEOUT_MS => 5000,
                CURLOPT_TIMEOUT_MS => 60000,
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
            $responses[] = [
                'status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'errno' => curl_errno($handle),
                'error' => curl_error($handle),
                'raw' => curl_multi_getcontent($handle),
            ];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        $this->assertCount(2, $responses);

        foreach ($responses as $response) {
            $this->assertSame(200, $response['status']);
        }

        $this->assertDatabaseCount('payment_events', 1);

        $this->assertDatabaseHas('payment_events', [
            'event_id' => 'evt_pending_race_001',
            'order_id' => null,
            'status' => PaymentStatus::PAID->value,
        ]);
    }

    public function test_fifty_paid_webhooks_deliver_one_key_under_concurrency(): void
    {
        $product = Product::factory()->create();

        $order = Order::factory()->create([
            'product_id' => $product->id,
            'sku' => $product->sku,
            'amount' => $product->price,
            'currency' => $product->currency,
            'status' => OrderStatus::CREATED,
        ]);

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ]);

        $url = 'http://127.0.0.1:8000/api/payment/webhook';

        $multiHandle = curl_multi_init();
        $handles = [];

        for ($i = 0; $i < 50; $i++) {
            $payload = [
                'event_id' => 'evt_paid_race_' . $i . '_' . fake()->uuid(),
                'order_id' => $order->public_id,
                'status' => \App\Enums\PaymentStatus::PAID->value,
                'amount' => $order->amount,
                'currency' => $order->currency,
                'created_at' => now()->toISOString(),
            ];

            $body = json_encode($payload, JSON_THROW_ON_ERROR);

            $signature = hash_hmac(
                'sha256',
                $body,
                config('services.payment.webhook_secret'),
            );

            $handle = curl_init($url);

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json',
                    "X-Signature: {$signature}",
                ],
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_CONNECTTIMEOUT_MS => 5000,
                CURLOPT_TIMEOUT_MS => 60000,
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
            $responses[] = [
                'status' => curl_getinfo($handle, CURLINFO_HTTP_CODE),
                'errno' => curl_errno($handle),
                'error' => curl_error($handle),
                'raw' => curl_multi_getcontent($handle),
            ];

            curl_multi_remove_handle($multiHandle, $handle);
            curl_close($handle);
        }

        curl_multi_close($multiHandle);

        $this->assertCount(50, $responses);

        foreach ($responses as $index => $response) {
            $this->assertSame(
                200,
                $response['status'],
                sprintf(
                    'Webhook #%d failed: HTTP=%d, errno=%d, error=%s, response=%s',
                    $index,
                    $response['status'],
                    $response['errno'],
                    $response['error'],
                    $response['raw'],
                ),
            );
        }

        /*
         * Delivery is asynchronous, so wait for the queue worker
         * to finish the single scheduled delivery job.
         */
        $deadline = microtime(true) + 30;

        do {
            $order->refresh();

            if ($order->status === OrderStatus::DELIVERED) {
                break;
            }

            usleep(250_000);
        } while (microtime(true) < $deadline);

        $order->refresh();

        $this->assertSame(
            OrderStatus::DELIVERED,
            $order->status,
        );

        $this->assertDatabaseCount('delivery_attempts', 1);

        $this->assertDatabaseHas('delivery_attempts', [
            'order_id' => $order->id,
            'status' => DeliveryAttemptStatus::SUCCESS->value,
            'inventory_item_id' => $item->id,
            'code' => $item->code,
        ]);

        $this->assertDatabaseCount('inventory_items', 1);

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => InventoryStatus::DELIVERED->value,
            'order_id' => $order->id,
        ]);
    }
}
