<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LabelTemplate>
 */
class LabelTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => implode(' ', fake()->words(3)),
            'description' => fake()->optional()->sentence(),
            'brand' => fake()->optional()->randomElement(['Avery', 'APLI', 'Herma']),
            'brand_code' => fake()->optional()->bothify('??####'),
            'page_width_mm' => 210.00,
            'page_height_mm' => 297.00,
            'columns' => 3,
            'rows' => 8,
            'label_width_mm' => 64.00,
            'label_height_mm' => 33.90,
            'margin_top_mm' => 13.50,
            'margin_left_mm' => 7.20,
            'horizontal_gap_mm' => 2.50,
            'vertical_gap_mm' => 0.00,
            'font_size' => 10,
            'line_height' => 1.30,
            'inner_padding_mm' => 2.00,
        ];
    }
}
