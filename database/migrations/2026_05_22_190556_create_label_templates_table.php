<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('label_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('brand')->nullable();          // Avery, APLI, Herma…
            $table->string('brand_code')->nullable();     // e.g. L7160, 4731

            // Page dimensions (mm)
            $table->decimal('page_width_mm', 6, 2)->default(210.00);
            $table->decimal('page_height_mm', 6, 2)->default(297.00);

            // Grid
            $table->unsignedTinyInteger('columns')->default(3);
            $table->unsignedTinyInteger('rows')->default(8);

            // Label dimensions (mm)
            $table->decimal('label_width_mm', 6, 2)->default(64.00);
            $table->decimal('label_height_mm', 6, 2)->default(33.90);

            // Page margins (mm)
            $table->decimal('margin_top_mm', 6, 2)->default(13.50);
            $table->decimal('margin_left_mm', 6, 2)->default(7.20);

            // Gaps between labels (mm)
            $table->decimal('horizontal_gap_mm', 6, 2)->default(2.50);
            $table->decimal('vertical_gap_mm', 6, 2)->default(0.00);

            // Typography
            $table->unsignedTinyInteger('font_size')->default(10);
            $table->decimal('line_height', 4, 2)->default(1.30);  // multiplier
            $table->decimal('inner_padding_mm', 5, 2)->default(2.00);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_templates');
    }
};
