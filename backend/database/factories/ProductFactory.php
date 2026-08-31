<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('TEST-####-????'),
            'name' => fake()->words(3, true),
            'type' => 'key',
            'price' => fake()->numberBetween(100, 5000),
            'currency' => 'RUB',
            'image' => 'assets/test.png',
            'is_active' => true,
        ];
    }
}
