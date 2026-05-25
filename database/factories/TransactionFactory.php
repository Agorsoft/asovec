<?php

namespace Database\Factories;

use App\Enums\TransactionSourceType;
use App\Enums\TransactionType;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(TransactionType::cases()),
            'amount' => fake()->randomFloat(2, 1, 300),
            'date' => fake()->date(),
            'description' => fake()->sentence(),
            'parent_transaction_id' => null,
            'category_id' => null,
            'source_type' => fake()->randomElement(TransactionSourceType::cases()),
            'payment_method' => fake()->randomElement(['Efectivo', 'Transferencia', 'Tarjeta', 'Bizum']),
            'reference_type' => null,
            'reference_id' => null,
            'receipt_number' => null,
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
