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
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sponsor_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('registration_type', 40)->index();
            $table->unsignedInteger('registration_sequence');
            $table->string('registration_number', 60)->index();
            $table->string('status', 30)->default('Pendiente')->index();
            $table->decimal('amount', 10, 2)->default(0);
            $table->decimal('surcharge_amount', 10, 2)->default(0);
            $table->string('payment_method', 50)->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'registration_sequence']);
            $table->index(['event_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('registrations');
    }
};
