<?php

namespace Database\Factories;

use App\Enums\DocumentType;
use App\Models\DocumentTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentTemplate>
 */
class DocumentTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'                => fake()->sentence(3),
            'description'         => fake()->optional()->sentence(),
            'type'                => fake()->randomElement(DocumentType::cases()),
            'file_path'           => 'documents/templates/sample.docx',
            'detected_variables'  => [],
        ];
    }
}
