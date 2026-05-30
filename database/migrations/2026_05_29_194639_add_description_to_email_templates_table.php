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
        Schema::table('email_templates', function (Blueprint $table) {
            // Solo renombrar si la columna 'body' existe (MySQL legacy, no SQLite test).
            if (Schema::hasColumn('email_templates', 'body')) {
                $table->renameColumn('body', 'body_html');
            }

            if (! Schema::hasColumn('email_templates', 'description')) {
                $table->text('description')->nullable()->after('body_html');
            }
        });
    }

    public function down(): void
    {
        Schema::table('email_templates', function (Blueprint $table) {
            if (Schema::hasColumn('email_templates', 'description')) {
                $table->dropColumn('description');
            }

            if (Schema::hasColumn('email_templates', 'body_html')) {
                $table->renameColumn('body_html', 'body');
            }
        });
    }
};
