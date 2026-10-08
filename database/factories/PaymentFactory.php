<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Orders\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'paypal_order_id' => fake()->regexify('[A-Z0-9]{17}'),
            'paypal_payer_id' => fake()->regexify('[A-Z0-9]{13}'),
            'status' => PaymentStatus::Completed,
            'amount' => fake()->randomFloat(2, 10, 500),
            'currency' => 'EUR',
            'fee' => fake()->randomFloat(2, 0.35, 5.00),
            'payer_email' => fake()->safeEmail(),
            'payer_name' => fake()->name(),
        ];
    }

    public function created(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Created,
            'paypal_capture_id' => null,
            'fee' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Completed,
            'paypal_capture_id' => fn () => fake()->regexify('[0-9A-Z]{17}'),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PaymentStatus::Failed,
        ]);
    }
}
