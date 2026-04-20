<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_catalogs')) {
            Schema::table('workshop_catalogs', function (Blueprint $table): void {
                if (!Schema::hasColumn('workshop_catalogs', 'attention_hours')) {
                    $table->string('attention_hours', 120)->nullable()->after('tipo');
                }

                if (!Schema::hasColumn('workshop_catalogs', 'location_label')) {
                    $table->string('location_label', 255)->nullable()->after('attention_hours');
                }
            });
        }

        if (Schema::hasTable('workshops')) {
            Schema::table('workshops', function (Blueprint $table): void {
                if (!Schema::hasColumn('workshops', 'attention_started_at')) {
                    $table->timestamp('attention_started_at')->nullable()->after('fecha_ingreso');
                }

                if (!Schema::hasColumn('workshops', 'service_location')) {
                    $table->string('service_location', 255)->nullable()->after('attention_started_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workshops')) {
            Schema::table('workshops', function (Blueprint $table): void {
                foreach (['service_location', 'attention_started_at'] as $column) {
                    if (Schema::hasColumn('workshops', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('workshop_catalogs')) {
            Schema::table('workshop_catalogs', function (Blueprint $table): void {
                foreach (['location_label', 'attention_hours'] as $column) {
                    if (Schema::hasColumn('workshop_catalogs', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
