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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('event_code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('event_type', 30)->default('Evento');
            $table->string('status', 30)->default('Abierto')->index();
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->date('registration_deadline')->nullable();
            $table->unsignedSmallInteger('max_companions_per_member')->default(0);
            $table->decimal('base_price', 10, 2)->default(0);
            $table->decimal('companion_surcharge', 10, 2)->default(0);
            $table->decimal('non_member_surcharge', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('last_registration_sequence')->default(0);
            $table->text('notes')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamps();

            $table->index(['start_date', 'status']);
            $table->index('registration_deadline');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
