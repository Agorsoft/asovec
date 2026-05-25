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
        Schema::create('quota_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('period_type', 20);
            $table->date('period_reference_date')->nullable();
            $table->decimal('default_amount', 10, 2)->default(0);
            $table->boolean('align_period_start')->default(false);
            $table->unsignedInteger('grace_days')->default(0);
            $table->unsignedInteger('refund_days')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['period_type', 'period_reference_date']);
            $table->index(['sort_order', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quota_types');
    }
};
