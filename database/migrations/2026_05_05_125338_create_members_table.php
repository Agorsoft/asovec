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
        Schema::create('members', function (Blueprint $table) {
            $table->id();
            $table->string('member_number')->nullable()->unique();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('dni')->nullable()->unique();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('postal_code')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 30)->default('No Definido');
            $table->string('photo_path')->nullable();
            $table->string('payment_method_id')->nullable();
            $table->string('sepa_mandate_reference')->nullable();
            $table->date('sepa_mandate_signed_at')->nullable();
            $table->string('sepa_mandate_status', 40)->nullable();
            $table->string('sepa_sequence_type', 30)->nullable();
            $table->string('bank_account_holder')->nullable();
            $table->string('bank_iban', 34)->nullable();
            $table->string('bank_bic', 11)->nullable();
            $table->string('member_type')->default('Socio');
            $table->string('status', 30)->default('Pendiente')->index();
            $table->date('joined_at')->nullable();
            $table->date('left_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['last_name', 'first_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('members');
    }
};
