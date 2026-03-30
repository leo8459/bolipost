<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('fuel_invoices', 'invoice_photo_path')) {
                $table->string('invoice_photo_path')->nullable()->after('siat_document_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fuel_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('fuel_invoices', 'invoice_photo_path')) {
                $table->dropColumn('invoice_photo_path');
            }
        });
    }
};
