<?php

namespace Database\Factories;

use App\Models\DocumentGeneration;
use App\Models\DocumentTemplate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentGeneration>
 */
class DocumentGenerationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'template_id' => DocumentTemplate::factory(),
            'generated_by' => User::factory(),
            'member_id' => null,
            'status' => 'completed',
            'output_path' => null,
            'error_message' => null,
        ];
    }
}
