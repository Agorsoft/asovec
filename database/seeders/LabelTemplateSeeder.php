<?php

namespace Database\Seeders;

use App\Models\LabelTemplate;
use Illuminate\Database\Seeder;

class LabelTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            // ── APLI ────────────────────────────────────────────────────────
            [
                'name' => '3×8 etiquetas (64×33,9 mm)',
                'description' => 'Hoja A4 con 24 etiquetas en cuadrícula 3×8',
                'brand' => 'APLI',
                'brand_code' => '01272',
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
            ],
            [
                'name' => '2×7 etiquetas (99×38 mm)',
                'description' => 'Hoja A4 con 14 etiquetas en cuadrícula 2×7',
                'brand' => 'APLI',
                'brand_code' => '01271',
                'page_width_mm' => 210.00,
                'page_height_mm' => 297.00,
                'columns' => 2,
                'rows' => 7,
                'label_width_mm' => 99.00,
                'label_height_mm' => 38.00,
                'margin_top_mm' => 15.00,
                'margin_left_mm' => 6.00,
                'horizontal_gap_mm' => 6.00,
                'vertical_gap_mm' => 0.00,
                'font_size' => 11,
                'line_height' => 1.35,
                'inner_padding_mm' => 2.50,
            ],
            [
                'name' => '3×7 etiquetas (70×42,3 mm)',
                'description' => 'Hoja A4 con 21 etiquetas en cuadrícula 3×7',
                'brand' => 'APLI',
                'brand_code' => '01276',
                'page_width_mm' => 210.00,
                'page_height_mm' => 297.00,
                'columns' => 3,
                'rows' => 7,
                'label_width_mm' => 70.00,
                'label_height_mm' => 42.30,
                'margin_top_mm' => 0.00,
                'margin_left_mm' => 0.00,
                'horizontal_gap_mm' => 0.00,
                'vertical_gap_mm' => 0.00,
                'font_size' => 11,
                'line_height' => 1.35,
                'inner_padding_mm' => 2.50,
            ],

            // ── Avery ────────────────────────────────────────────────────────
            [
                'name' => '3×7 etiquetas (63,5×38,1 mm)',
                'description' => 'Hoja A4 con 21 etiquetas — formato europeo estándar',
                'brand' => 'Avery',
                'brand_code' => 'L7160',
                'page_width_mm' => 210.00,
                'page_height_mm' => 297.00,
                'columns' => 3,
                'rows' => 7,
                'label_width_mm' => 63.50,
                'label_height_mm' => 38.10,
                'margin_top_mm' => 15.15,
                'margin_left_mm' => 7.25,
                'horizontal_gap_mm' => 2.55,
                'vertical_gap_mm' => 0.00,
                'font_size' => 11,
                'line_height' => 1.30,
                'inner_padding_mm' => 2.00,
            ],
            [
                'name' => '4×7 etiquetas (47,5×25,4 mm)',
                'description' => 'Hoja A4 con 28 etiquetas pequeñas — 4×7',
                'brand' => 'Avery',
                'brand_code' => 'L7162',
                'page_width_mm' => 210.00,
                'page_height_mm' => 297.00,
                'columns' => 4,
                'rows' => 7,
                'label_width_mm' => 47.50,
                'label_height_mm' => 25.40,
                'margin_top_mm' => 21.00,
                'margin_left_mm' => 10.00,
                'horizontal_gap_mm' => 2.50,
                'vertical_gap_mm' => 0.00,
                'font_size' => 9,
                'line_height' => 1.25,
                'inner_padding_mm' => 1.50,
            ],

            // ── Herma ────────────────────────────────────────────────────────
            [
                'name' => '3×8 etiquetas (63,5×33,9 mm)',
                'description' => 'Hoja A4 con 24 etiquetas — Herma formato postal',
                'brand' => 'Herma',
                'brand_code' => '4474',
                'page_width_mm' => 210.00,
                'page_height_mm' => 297.00,
                'columns' => 3,
                'rows' => 8,
                'label_width_mm' => 63.50,
                'label_height_mm' => 33.90,
                'margin_top_mm' => 13.50,
                'margin_left_mm' => 9.75,
                'horizontal_gap_mm' => 2.54,
                'vertical_gap_mm' => 0.00,
                'font_size' => 10,
                'line_height' => 1.30,
                'inner_padding_mm' => 2.00,
            ],
        ];

        foreach ($templates as $data) {
            LabelTemplate::updateOrCreate(
                ['brand' => $data['brand'], 'brand_code' => $data['brand_code']],
                $data,
            );
        }
    }
}
