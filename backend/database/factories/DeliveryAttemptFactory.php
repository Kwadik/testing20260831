<?php

namespace Database\Factories;

use App\Enums\DeliveryAttemptStatus;
use App\Models\DeliveryAttempt;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeliveryAttempt>
 */
class DeliveryAttemptFactory extends Factory
{
    protected $model = DeliveryAttempt::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'provider' => 'inventory',
            'request_id' => fake()->uuid(),
            'status' => DeliveryAttemptStatus::PENDING,
            'inventory_item_id' => null,
            'code' => null,
            'error' => null,
            'started_at' => null,
            'finished_at' => null,
        ];
    }
}
