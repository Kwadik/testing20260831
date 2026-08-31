<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateOrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_order_by_sku(): void
    {
        $product = Product::factory()->create([
            'sku' => 'KEY-CS2-PRIME',
            'price' => 1290,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'sku' => $product->sku,
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', 'created')
            ->assertJsonPath('data.sku', 'KEY-CS2-PRIME')
            ->assertJsonPath('data.amount', 1290)
            ->assertJsonPath('data.currency', 'RUB');

        $this->assertDatabaseHas('orders', [
            'product_id' => $product->id,
            'sku' => 'KEY-CS2-PRIME',
            'amount' => 1290,
            'currency' => 'RUB',
            'status' => 'created',
        ]);
    }

    public function test_client_cannot_override_product_price(): void
    {
        $product = Product::factory()->create([
            'sku' => 'KEY-CS2-PRIME',
            'price' => 1290,
            'currency' => 'RUB',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/orders', [
            'sku' => $product->sku,
            'price' => 1,
            'amount' => 1,
            'currency' => 'USD',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount', 1290)
            ->assertJsonPath('data.currency', 'RUB');

        $this->assertDatabaseHas('orders', [
            'product_id' => $product->id,
            'amount' => 1290,
            'currency' => 'RUB',
        ]);
    }

    public function test_order_cannot_be_created_for_unknown_sku(): void
    {
        $response = $this->postJson('/api/orders', [
            'sku' => 'UNKNOWN-SKU',
        ]);

        $response->assertNotFound();
    }
}
