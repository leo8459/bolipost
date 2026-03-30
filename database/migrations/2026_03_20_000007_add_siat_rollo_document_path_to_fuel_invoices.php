<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fuel_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('fuel_invoices', 'siat_rollo_document_path')) {
                $table->string('siat_rollo_document_path')->nullable()->after('siat_document_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('fuel_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('fuel_invoices', 'siat_rollo_document_path')) {
                $table->dropColumn('siat_rollo_document_path');
            }
        });
    }
};
