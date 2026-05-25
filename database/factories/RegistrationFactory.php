<?php

namespace Database\Factories;

use App\Enums\RegistrationStatus;
use App\Enums\RegistrationType;
use App\Models\Event;
use App\Models\Member;
use App\Models\Registration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Registration>
 */
class RegistrationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'member_id' => Member::query()->inRandomOrder()->value('id')
                ?? Member::query()->create([
                    'member_number' => (string) fake()->unique()->numberBetween(10000, 99999),
                    'first_name' => fake()->firstName(),
                    'last_name' => fake()->lastName(),
                    'member_type' => 'No Socio',
                    'status' => 'Pendiente',
                ])->id,
            'sponsor_member_id' => null,
            'registration_type' => RegistrationType::NoSocio,
            'registration_sequence' => fake()->numberBetween(1, 9999),
            'registration_number' => strtoupper(fake()->bothify('EVT-###-####')),
            'status' => RegistrationStatus::Pendiente,
            'amount' => fake()->randomFloat(2, 5, 70),
            'surcharge_amount' => fake()->randomFloat(2, 0, 20),
            'payment_method' => null,
            'paid_at' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
