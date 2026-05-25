<?php

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\EventType;
use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('+2 days', '+2 months');

        return [
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'event_type' => fake()->randomElement(EventType::cases()),
            'status' => EventStatus::Abierto,
            'start_date' => $startDate,
            'end_date' => (clone $startDate)->modify('+2 hours'),
            'registration_deadline' => fake()->dateTimeBetween('+1 day', '+1 month'),
            'max_companions_per_member' => fake()->numberBetween(0, 3),
            'base_price' => fake()->randomFloat(2, 5, 60),
            'companion_surcharge' => fake()->randomFloat(2, 0, 20),
            'non_member_surcharge' => fake()->randomFloat(2, 0, 25),
            'is_active' => true,
            'last_registration_sequence' => 0,
            'notes' => fake()->optional()->sentence(),
            'cancellation_reason' => null,
        ];
    }
}
