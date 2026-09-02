<?php

namespace Tests\Feature\Services;

use App\Models\InventoryItem;
use App\Models\Order;
use App\Models\Product;
use App\Services\Delivery\ProviderB;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderBTest extends TestCase
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

        InventoryItem::factory()->create([
            'product_id' => $product->id,
            'code' => 'BXXX-YYYY-ZZZZ',
            'status' => 'available',
            'order_id' => null,
        ]);

        $provider = app(ProviderB::class);

        $first = $provider->issue(
            requestId: 'req_provider_b_001',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $second = $provider->issue(
            requestId: 'req_provider_b_001',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $this->assertSame('ok', $first->status);
        $this->assertSame('BXXX-YYYY-ZZZZ', $first->code);

        $this->assertSame('ok', $second->status);
        $this->assertSame('BXXX-YYYY-ZZZZ', $second->code);

        $this->assertDatabaseCount('provider_issuances', 1);
        $this->assertDatabaseCount('inventory_items', 1);

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_b',
            'request_id' => 'req_provider_b_001',
            'order_id' => $order->id,
            'status' => 'success',
            'code' => 'BXXX-YYYY-ZZZZ',
        ]);

        $this->assertDatabaseHas('inventory_items', [
            'product_id' => $product->id,
            'status' => 'delivered',
            'order_id' => $order->id,
            'code' => 'BXXX-YYYY-ZZZZ',
        ]);
    }

    public function test_out_of_stock_returns_error(): void
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

        $provider = app(ProviderB::class);

        $result = $provider->issue(
            requestId: 'req_provider_b_empty',
            sku: $product->sku,
            orderId: $order->public_id,
        );

        $this->assertSame('error', $result->status);
        $this->assertNull($result->code);
        $this->assertSame('out_of_stock', $result->reason);

        $this->assertDatabaseHas('provider_issuances', [
            'provider' => 'provider_b',
            'request_id' => 'req_provider_b_empty',
            'order_id' => $order->id,
            'status' => 'error',
            'reason' => 'out_of_stock',
        ]);
    }
}
