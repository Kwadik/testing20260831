<?php

namespace Tests\Feature\Api;

use App\Enums\PaymentStatus;
use App\Models\PaymentEvent;
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
}
