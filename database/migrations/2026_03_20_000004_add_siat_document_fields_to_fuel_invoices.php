<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fuel_invoices')) {
            return;
        }

        Schema::table('fuel_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('fuel_invoices', 'siat_source_url')) {
                $table->string('siat_source_url')->nullable()->after('monto_total');
            }
            if (!Schema::hasColumn('fuel_invoices', 'siat_snapshot_json')) {
                $table->json('siat_snapshot_json')->nullable()->after('siat_source_url');
            }
            if (!Schema::hasColumn('fuel_invoices', 'siat_document_path')) {
                $table->string('siat_document_path')->nullable()->after('siat_snapshot_json');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fuel_invoices')) {
            return;
        }

        Schema::table('fuel_invoices', function (Blueprint $table) {
            if (Schema::hasColumn('fuel_invoices', 'siat_document_path')) {
                $table->dropColumn('siat_document_path');
            }
            if (Schema::hasColumn('fuel_invoices', 'siat_snapshot_json')) {
                $table->dropColumn('siat_snapshot_json');
            }
            if (Schema::hasColumn('fuel_invoices', 'siat_source_url')) {
                $table->dropColumn('siat_source_url');
            }
        });
    }
};
