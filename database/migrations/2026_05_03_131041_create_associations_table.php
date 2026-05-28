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
        Schema::create('associations', function (Blueprint $table) {
            $table->id();

            // Datos generales
            $table->string('name');
            $table->string('tax_id')->nullable();
            $table->string('registration_entity')->nullable();
            $table->string('registration_number')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // Dirección
            $table->string('address')->nullable();
            $table->string('postal_code', 10)->nullable();
            $table->string('city')->nullable();
            $table->string('province')->nullable();
            $table->string('country')->nullable()->default('España');

            // Presencia web
            $table->string('website')->nullable();
            $table->string('logo_path')->nullable();

            // Pagos
            $table->json('payment_methods')->nullable();
            $table->string('payment_period')->nullable();
            $table->string('receipt_number_mask')->default('{Y:2}-{N:4}');
            $table->unsignedBigInteger('last_member_number')->default(0);
            $table->unsignedBigInteger('last_receipt_number')->default(0);

            // Notas
            $table->text('observations')->nullable();

            // Datos bancarios
            $table->string('iban')->nullable();
            $table->string('bic')->nullable();
            $table->string('creditor_identifier')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_address')->nullable();
            $table->string('bank_postal_code', 10)->nullable();
            $table->string('bank_city')->nullable();
            $table->string('bank_province')->nullable();
            $table->string('bank_country')->nullable()->default('España');

            // Datos email
            $table->boolean('use_email')->default(false);
            $table->string('mail_mailer')->nullable();
            $table->string('mail_host')->nullable();
            $table->unsignedSmallInteger('mail_port')->nullable();
            $table->string('mail_encryption')->nullable();
            $table->string('mail_username')->nullable();
            $table->text('mail_password')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();

            $table->timestamp('installed_at')->nullable();
            $table->json('role_permissions')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('associations');
    }
};
