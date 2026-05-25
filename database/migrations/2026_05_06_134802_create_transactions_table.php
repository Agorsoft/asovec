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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->index();
            $table->decimal('amount', 10, 2);
            $table->date('date');
            $table->string('description');
            $table->foreignId('parent_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->string('source_type', 50)->nullable()->index();
            $table->string('payment_method', 50)->nullable();
            $table->nullableMorphs('reference');
            $table->string('receipt_number')->nullable()->index();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['date', 'type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
