<?php

namespace Database\Factories;

use App\Models\Association;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Association>
 */
class AssociationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'tax_id' => strtoupper(fake()->bothify('?########')),
            'registration_entity' => fake()->optional()->words(3, true),
            'registration_number' => fake()->optional()->numerify('######'),
            'email' => fake()->optional()->companyEmail(),
            'phone' => fake()->optional()->phoneNumber(),
            'address' => fake()->optional()->streetAddress(),
            'postal_code' => fake()->optional()->postcode(),
            'city' => fake()->optional()->city(),
            'province' => fake()->optional()->state(),
            'country' => 'España',
            'website' => fake()->optional()->url(),
            'logo_path' => null,
            'payment_methods' => null,
            'observations' => fake()->optional()->sentence(),
            'payment_period' => null,
            'receipt_number_mask' => '{Y:2}-{N:4}',
            'last_member_number' => 0,
            'last_receipt_number' => 0,
            'iban' => null,
            'bic' => null,
            'creditor_identifier' => null,
            'bank_name' => null,
            'bank_branch' => null,
            'bank_address' => null,
            'bank_postal_code' => null,
            'bank_city' => null,
            'bank_province' => null,
            'bank_country' => 'España',
            'installed_at' => null,
        ];
    }
}
