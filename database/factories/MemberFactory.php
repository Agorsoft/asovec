<?php

namespace Database\Factories;

use App\Enums\Gender;
use App\Enums\MemberStatus;
use App\Enums\MemberType;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Member>
 */
class MemberFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'member_number' => (string) fake()->unique()->numberBetween(1, 10000),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->address(),
            'city' => fake()->optional()->city(),
            'province' => fake()->optional()->state(),
            'postal_code' => fake()->optional()->postcode(),
            'birth_date' => fake()->optional()->dateTimeBetween('-60 years', '-18 years'),
            'gender' => fake()->randomElement(Gender::cases()),
            'member_type' => MemberType::Socio,
            'status' => MemberStatus::Activo,
            'joined_at' => fake()->dateTimeBetween('-5 years'),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
