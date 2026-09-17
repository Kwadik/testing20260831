<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_status_can_be_read_by_public_id(): void
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

        $response = $this->getJson(
            "/api/orders/{$order->public_id}",
        );

        $response
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $order->public_id,
                    'status' => OrderStatus::CREATED->value,
                    'sku' => $order->sku,
                    'amount' => $order->amount,
                    'currency' => $order->currency,
                ],
            ]);
    }

    public function test_unknown_order_status_returns_not_found(): void
    {
        $this->getJson(
            '/api/orders/11111111-1111-1111-1111-111111111111',
        )->assertNotFound();
    }
}
