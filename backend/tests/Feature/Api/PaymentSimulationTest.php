<?php

namespace Tests\Feature\Api;

use App\Enums\OrderStatus;
use App\Jobs\DeliverOrderJob;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PaymentSimulationTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulated_payment_marks_order_as_paid(): void
    {
        Queue::fake();

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

        $response = $this->postJson(
            "/api/orders/{$order->public_id}/pay",
        );

        $response
            ->assertOk()
            ->assertJson([
                'status' => 'accepted',
            ]);

        $this->assertDatabaseHas('orders', [
            'id' => $order->id,
            'status' => OrderStatus::PAID->value,
        ]);

        $this->assertDatabaseCount('payment_events', 1);

        Queue::assertPushed(DeliverOrderJob::class);
    }

    public function test_simulated_payment_uses_order_amount_and_currency(): void
    {
        Queue::fake();

        $product = Product::factory()->create([
            'price' => 1290,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $order = Order::factory()
            ->forProduct($product)
            ->create([
                'status' => OrderStatus::CREATED,
                'amount' => 1290,
                'currency' => 'RUB',
            ]);

        $this->postJson(
            "/api/orders/{$order->public_id}/pay",
        )->assertOk();

        $this->assertDatabaseHas('payment_events', [
            'order_id' => $order->id,
            'status' => 'paid',
            'amount' => 1290,
            'currency' => 'RUB',
        ]);
    }

    public function test_simulated_payment_for_unknown_order_returns_not_found(): void
    {
        $this->postJson(
            '/api/orders/11111111-1111-1111-1111-111111111111/pay',
        )->assertNotFound();

        $this->assertDatabaseCount('payment_events', 0);
    }
}
