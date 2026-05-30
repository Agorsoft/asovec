<?php

namespace Database\Factories;

use App\Enums\EmailTemplateType;
use App\Models\EmailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(EmailTemplateType::cases());

        return [
            'name' => $this->faker->sentence(3),
            'type' => $type,
            'subject' => $this->faker->sentence(6),
            'body_html' => '<p>'.$this->faker->paragraph().'</p>',
            'description' => $this->faker->optional()->sentence(),
            'is_active' => false,
        ];
    }

    public function active(): static
    {
        return $this->state(['is_active' => true]);
    }

    public function forType(EmailTemplateType $type): static
    {
        return $this->state(['type' => $type]);
    }
}
