<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Order;
use App\Support\Orders\OrderStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'status' => OrderStatus::Pending,
            'total_amount' => fake()->randomFloat(2, 10, 1000),
        ];
    }
}
