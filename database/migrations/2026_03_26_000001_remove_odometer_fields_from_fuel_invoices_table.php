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
            foreach ([
                'odometer_photo_captured_at',
                'odometer_photo_path',
            ] as $column) {
                if (Schema::hasColumn('fuel_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fuel_invoices')) {
            return;
        }

        Schema::table('fuel_invoices', function (Blueprint $table) {
            if (!Schema::hasColumn('fuel_invoices', 'odometer_photo_path')) {
                $table->string('odometer_photo_path')->nullable()->after('fuel_recorded_at');
            }
            if (!Schema::hasColumn('fuel_invoices', 'odometer_photo_captured_at')) {
                $table->timestamp('odometer_photo_captured_at')->nullable()->after('odometer_photo_path');
            }
        });
    }
};
