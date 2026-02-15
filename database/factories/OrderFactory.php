<?php

namespace Database\Factories;

use App\Enums\PlanEnum;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'order_id' => 'order_'.fake()->text(12),
            'user_id' => User::factory(),
            'invoice_number' => null,
            'description' => 'Yearly Plan',
            'plan' => PlanEnum::Yearly,
            'amount' => 3999,
            'status' => 'pending',
            'razorpay_payment_id' => null,
            'razorpay_order_id' => null,
            'razorpay_signature' => null,
            'email' => fake()->safeEmail(),
            'address_line_one' => null,
            'address_line_two' => null,
            'city' => null,
            'postal_code' => null,
            'state' => null,
            'country' => null,
            'terms_accepted' => now(),
            'plan_starts_at' => null,
            'plan_ends_at' => null,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
            'plan_starts_at' => now(),
            'invoice_number' => Order::paid()->count() + 1,
        ]);
    }

    public function newsletter(): static
    {
        return $this->state(fn (array $attributes) => [
            'plan' => PlanEnum::Newsletter,
        ]);
    }
}
