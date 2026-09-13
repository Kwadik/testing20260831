<?php

namespace Tests\Feature\Services;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\Delivery\ProviderA;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderATest extends TestCase
{
    use RefreshDatabase;

    public function test_same_request_id_returns_same_code(): void
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

        $item = InventoryItem::factory()->create([
            'product_id' => $product->id,
            'code' => 'LFXC-TNCS-BPCD',
            'status' => 'available',
            'order_id' => null,
        ]);

        $provider = app(ProviderA::class);

        $first = $provider->issue(
            requestId: 'req_00123-1',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $second = $provider->issue(
            requestId: 'req_00123-1',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $this->assertSame('ok', $first->status);
        $this->assertSame('ok', $second->status);

        $this->assertSame(
            'LFXC-TNCS-BPCD',
            $first->code,
        );

        $this->assertSame(
            $first->code,
            $second->code,
        );

        $this->assertDatabaseCount(
            'inventory_items',
            1,
        );

        $this->assertDatabaseHas('inventory_items', [
            'id' => $item->id,
            'status' => 'delivered',
            'order_id' => $order->id,
        ]);

        $this->assertDatabaseCount(
            'provider_issuances',
            1,
        );
    }

    public function test_timeout_after_issue_returns_same_code_on_retry(): void
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
            'code' => 'LFXC-TNCS-BPCD',
            'status' => 'available',
            'order_id' => null,
        ]);

        $provider = app(ProviderA::class);

        try {
            $provider->issue(
                requestId: 'req_timeout_001',
                sku: $product->sku,
                orderId: $order->public_id,
            );

            $this->fail('Provider A did not timeout.');
        } catch (\App\Exceptions\ProviderTimeoutException) {
            // Expected.
        }

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_a',
            'request_id' => 'req_timeout_001',
            'order_id' => $order->id,
            'status' => 'success',
            'code' => 'LFXC-TNCS-BPCD',
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'status' => 'delivered',
            'order_id' => $order->id,
        ]);

        config()->set(
            'delivery.provider_a.mode',
            'normal',
        );

        $retry = $provider->issue(
            requestId: 'req_timeout_001',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $this->assertSame('ok', $retry->status);
        $this->assertSame('LFXC-TNCS-BPCD', $retry->code);

        $this->assertDatabaseCount('provider_issuances', 1);
        $this->assertDatabaseCount('inventory_items', 1);
    }
}
