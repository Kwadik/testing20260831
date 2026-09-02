<?php

namespace Tests\Feature\Services;

use App\Exceptions\ProviderTimeoutException;
use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\Delivery\DeliveryOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrchestratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_provider_a_delivery_does_not_call_provider_b(): void
    {
        $product = Product::factory()->create([
            'sku' => 'STEAM-TOPUP-500',
            'price' => 500,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => 'paid',
            ]);

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'code' => 'AAAA-BBBB-CCCC',
            'status' => 'available',
            'order_id' => null,
        ]);

        $orchestrator = app(DeliveryOrchestrator::class);

        $result = $orchestrator->issue(
            requestId: 'req_orchestrator_a_success',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $this->assertSame('ok', $result->status);
        $this->assertSame('AAAA-BBBB-CCCC', $result->code);

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_a',
            'request_id' => 'req_orchestrator_a_success',
            'status' => 'success',
            'code' => 'AAAA-BBBB-CCCC',
        ]);

        $this->assertDatabaseMissing('provider_issuances', [
            'provider' => 'provider_b',
            'request_id' => 'req_orchestrator_a_success',
        ]);
    }

    public function test_provider_a_out_of_stock_falls_back_to_provider_b(): void
    {
        $product = Product::factory()->create([
            'sku' => 'STEAM-TOPUP-500',
            'price' => 500,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => 'paid',
            ]);

        /*
         * Provider A and Provider B currently use the same inventory pool.
         *
         * Therefore this scenario cannot distinguish A failure from B failure
         * with the current provider implementations.
         *
         * The provider-level fallback test will be added once provider
         * responses can be configured independently.
         */
        $orchestrator = app(DeliveryOrchestrator::class);

        $result = $orchestrator->issue(
            requestId: 'req_orchestrator_fallback',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $this->assertSame('error', $result->status);
        $this->assertSame('out_of_stock', $result->reason);

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_a',
            'request_id' => 'req_orchestrator_fallback',
            'status' => 'error',
            'reason' => 'out_of_stock',
        ]);

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_b',
            'request_id' => 'req_orchestrator_fallback',
            'status' => 'error',
            'reason' => 'out_of_stock',
        ]);
    }

    public function test_provider_a_timeout_is_not_sent_to_provider_b(): void
    {
        config()->set(
            'delivery.provider_a.mode',
            'timeout_after_issue',
        );

        $product = Product::factory()->create([
            'sku' => 'STEAM-TOPUP-500',
            'price' => 500,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => 'paid',
            ]);

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'code' => 'TIME-OUT-A-001',
            'status' => 'available',
            'order_id' => null,
        ]);

        $orchestrator = app(DeliveryOrchestrator::class);

        try {
            $orchestrator->issue(
                requestId: 'req_orchestrator_timeout',
                sku: $product->sku,
                orderId: $order->public_id,
            );

            $this->fail('Provider A did not timeout.');
        } catch (ProviderTimeoutException) {
            // Expected.
        }

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_a',
            'request_id' => 'req_orchestrator_timeout',
            'status' => 'success',
            'code' => 'TIME-OUT-A-001',
        ]);

        $this->assertDatabaseMissing('provider_issuances', [
            'provider' => 'provider_b',
            'request_id' => 'req_orchestrator_timeout',
        ]);

        config()->set(
            'delivery.provider_a.mode',
            'normal',
        );
    }

    public function test_provider_a_definitive_error_falls_back_to_provider_b(): void
    {
        config()->set(
            'delivery.provider_a.mode',
            'error',
        );

        config()->set(
            'delivery.provider_b.mode',
            'normal',
        );

        $product = Product::factory()->create([
            'sku' => 'STEAM-TOPUP-500',
            'price' => 500,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => 'paid',
            ]);

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'code' => 'BBBB-CCCC-DDDD',
            'status' => 'available',
            'order_id' => null,
        ]);

        $orchestrator = app(DeliveryOrchestrator::class);

        $result = $orchestrator->issue(
            requestId: 'req_orchestrator_a_error',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $this->assertSame('ok', $result->status);
        $this->assertSame('BBBB-CCCC-DDDD', $result->code);

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_a',
            'request_id' => 'req_orchestrator_a_error',
            'order_id' => $order->id,
            'status' => 'error',
            'reason' => 'provider_error',
        ]);

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_b',
            'request_id' => 'req_orchestrator_a_error',
            'order_id' => $order->id,
            'status' => 'success',
            'code' => 'BBBB-CCCC-DDDD',
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $product->id,
            'code' => 'BBBB-CCCC-DDDD',
            'status' => 'delivered',
            'order_id' => $order->id,
        ]);

        config()->set(
            'delivery.provider_a.mode',
            'normal',
        );

        config()->set(
            'delivery.provider_b.mode',
            'normal',
        );
    }
}
