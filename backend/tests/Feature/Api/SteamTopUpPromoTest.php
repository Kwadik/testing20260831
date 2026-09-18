<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Models\PromoCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SteamTopUpPromoTest extends TestCase
{
    use RefreshDatabase;

    public function test_steam_top_up_can_be_created_without_promo_code(): void
    {
        $response = $this->postJson('/api/steam-topups', [
            'amount' => 1000,
            'currency' => 'RUB',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.status', OrderStatus::CREATED->value)
            ->assertJsonPath('data.sku', 'STEAM-TOPUP')
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.currency', 'RUB')
            ->assertJsonPath('data.promo_code', null)
            ->assertJsonPath('data.discount_amount', 0);

        $this->assertDatabaseHas('orders', [
            'sku' => 'STEAM-TOPUP',
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => null,
            'discount_amount' => 0,
            'status' => OrderStatus::CREATED->value,
        ]);
    }

    public function test_percent_promo_calculates_discount_on_server(): void
    {
        PromoCode::create([
            'code' => 'TEST10',
            'type' => 'percent',
            'value' => 10,
            'max_uses' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);
        $response = $this->postJson('/api/steam-topups', [
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => 'TEST10',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount', 900)
            ->assertJsonPath('data.discount_amount', 100)
            ->assertJsonPath('data.promo_code', 'TEST10');

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'TEST10',
            'used_count' => 1,
        ]);
    }

    public function test_amount_promo_calculates_discount_on_server(): void
    {
        PromoCode::create([
            'code' => 'TEST500',
            'type' => 'amount',
            'value' => 500,
            'currency' => 'RUB',
            'max_uses' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);
        $response = $this->postJson('/api/steam-topups', [
            'amount' => 1500,
            'currency' => 'RUB',
            'promo_code' => 'TEST500',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount', 1000)
            ->assertJsonPath('data.discount_amount', 500)
            ->assertJsonPath('data.promo_code', 'TEST500');
    }

    public function test_amount_promo_cannot_be_used_with_another_currency(): void
    {
        PromoCode::create([
            'code' => 'TEST500',
            'type' => 'amount',
            'value' => 500,
            'currency' => 'RUB',
            'max_uses' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);
        $response = $this->postJson('/api/steam-topups', [
            'amount' => 1500,
            'currency' => 'USD',
            'promo_code' => 'TEST500',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('promo_code');

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'TEST500',
            'used_count' => 0,
        ]);

        $this->assertDatabaseMissing('orders', [
            'promo_code' => 'TEST500',
        ]);
    }

    public function test_invalid_promo_code_does_not_create_order(): void
    {
        $response = $this->postJson('/api/steam-topups', [
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => 'DOESNOTEXIST',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('promo_code');

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_inactive_promo_code_cannot_be_used(): void
    {
        PromoCode::create([
            'code' => 'INACTIVE',
            'type' => 'percent',
            'value' => 10,
            'max_uses' => 100,
            'used_count' => 0,
            'is_active' => false,
        ]);
        $response = $this->postJson('/api/steam-topups', [
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => 'INACTIVE',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('promo_code');

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'INACTIVE',
            'used_count' => 0,
        ]);
    }

    public function test_exhausted_promo_code_cannot_be_used(): void
    {
        PromoCode::create([
            'code' => 'LIMIT',
            'type' => 'percent',
            'value' => 25,
            'max_uses' => 3,
            'used_count' => 3,
            'is_active' => true,
        ]);
        $response = $this->postJson('/api/steam-topups', [
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => 'LIMIT',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonValidationErrors('promo_code');

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'LIMIT',
            'used_count' => 3,
        ]);

        $this->assertDatabaseMissing('orders', [
            'promo_code' => 'LIMIT',
        ]);
    }

    public function test_promo_usage_is_incremented_once_per_created_order(): void
    {
        PromoCode::create([
            'code' => 'ONCE',
            'type' => 'percent',
            'value' => 50,
            'max_uses' => 1,
            'used_count' => 0,
            'is_active' => true,
        ]);
        $firstResponse = $this->postJson('/api/steam-topups', [
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => 'ONCE',
        ]);

        $firstResponse
            ->assertCreated()
            ->assertJsonPath('data.amount', 500);

        $secondResponse = $this->postJson('/api/steam-topups', [
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => 'ONCE',
        ]);

        $secondResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('promo_code');

        $this->assertDatabaseHas('promo_codes', [
            'code' => 'ONCE',
            'used_count' => 1,
        ]);

        $this->assertDatabaseCount('orders', 1);
    }

    public function test_discount_cannot_make_order_amount_negative(): void
    {
        PromoCode::create([
            'code' => 'BIGDISCOUNT',
            'type' => 'amount',
            'value' => 5000,
            'currency' => 'RUB',
            'max_uses' => 100,
            'used_count' => 0,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/steam-topups', [
            'amount' => 1000,
            'currency' => 'RUB',
            'promo_code' => 'BIGDISCOUNT',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.amount', 0)
            ->assertJsonPath('data.discount_amount', 1000);
    }
}
