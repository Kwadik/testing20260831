<?php

namespace Database\Factories;

use App\Enums\InventoryStatus;
use App\Models\InventoryItem;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InventoryItem>
 */
class InventoryItemFactory extends Factory
{
    protected $model = InventoryItem::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'code' => strtoupper(fake()->bothify('????-????-????')),
            'status' => InventoryStatus::AVAILABLE,
            'order_id' => null,
        ];
    }
}
