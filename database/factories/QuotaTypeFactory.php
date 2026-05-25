<?php

namespace Database\Factories;

use App\Enums\PaymentPeriod;
use App\Models\QuotaType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotaType>
 */
class QuotaTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->word(),
            'description' => fake()->optional()->sentence(),
            'period_type' => fake()->randomElement(PaymentPeriod::cases()),
            'period_reference_date' => fake()->optional()->date(),
            'default_amount' => fake()->randomFloat(2, 10, 100),
            'align_period_start' => false,
            'grace_days' => fake()->numberBetween(0, 30),
            'refund_days' => fake()->numberBetween(0, 90),
            'sort_order' => fake()->numberBetween(1, 10),
        ];
    }
}
