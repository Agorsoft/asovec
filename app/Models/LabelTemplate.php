<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LabelTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'brand',
        'brand_code',
        'page_width_mm',
        'page_height_mm',
        'columns',
        'rows',
        'label_width_mm',
        'label_height_mm',
        'margin_top_mm',
        'margin_left_mm',
        'horizontal_gap_mm',
        'vertical_gap_mm',
        'font_size',
        'line_height',
        'inner_padding_mm',
    ];

    protected $casts = [
        'page_width_mm' => 'float',
        'page_height_mm' => 'float',
        'columns' => 'integer',
        'rows' => 'integer',
        'label_width_mm' => 'float',
        'label_height_mm' => 'float',
        'margin_top_mm' => 'float',
        'margin_left_mm' => 'float',
        'horizontal_gap_mm' => 'float',
        'vertical_gap_mm' => 'float',
        'font_size' => 'integer',
        'line_height' => 'float',
        'inner_padding_mm' => 'float',
    ];

    /** Total de etiquetas por hoja. */
    public function getLabelsPerPageAttribute(): int
    {
        return $this->columns * $this->rows;
    }

    /** Descripción formateada para selects. */
    public function getDisplayNameAttribute(): string
    {
        $brand = $this->brand
            ? '['.$this->brand.($this->brand_code ? ' '.$this->brand_code : '').'] '
            : '';

        return "{$brand}{$this->name} — {$this->columns}×{$this->rows} ({$this->label_width_mm}×{$this->label_height_mm} mm)";
    }
}
