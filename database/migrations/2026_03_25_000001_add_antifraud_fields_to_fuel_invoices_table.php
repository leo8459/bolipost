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
            if (!Schema::hasColumn('fuel_invoices', 'fuel_latitude')) {
                $table->decimal('fuel_latitude', 10, 7)->nullable()->after('invoice_photo_path');
            }
            if (!Schema::hasColumn('fuel_invoices', 'fuel_longitude')) {
                $table->decimal('fuel_longitude', 10, 7)->nullable()->after('fuel_latitude');
            }
            if (!Schema::hasColumn('fuel_invoices', 'fuel_location_label')) {
                $table->string('fuel_location_label')->nullable()->after('fuel_longitude');
            }
            if (!Schema::hasColumn('fuel_invoices', 'fuel_recorded_at')) {
                $table->timestamp('fuel_recorded_at')->nullable()->after('fuel_location_label');
            }
            if (!Schema::hasColumn('fuel_invoices', 'odometer_photo_path')) {
                $table->string('odometer_photo_path')->nullable()->after('fuel_recorded_at');
            }
            if (!Schema::hasColumn('fuel_invoices', 'odometer_photo_captured_at')) {
                $table->timestamp('odometer_photo_captured_at')->nullable()->after('odometer_photo_path');
            }
            if (!Schema::hasColumn('fuel_invoices', 'antifraud_payload_json')) {
                $table->json('antifraud_payload_json')->nullable()->after('odometer_photo_captured_at');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('fuel_invoices')) {
            return;
        }

        Schema::table('fuel_invoices', function (Blueprint $table) {
            foreach ([
                'antifraud_payload_json',
                'odometer_photo_captured_at',
                'odometer_photo_path',
                'fuel_recorded_at',
                'fuel_location_label',
                'fuel_longitude',
                'fuel_latitude',
            ] as $column) {
                if (Schema::hasColumn('fuel_invoices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
