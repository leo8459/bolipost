<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maintenance_types')) {
            return;
        }

        if (!Schema::hasColumn('maintenance_types', 'categoria')) {
            Schema::table('maintenance_types', function (Blueprint $table) {
                $table->string('categoria', 40)
                    ->default('preventivo_km')
                    ->after('maintenance_form_type');
            });
        }

        DB::table('maintenance_types')
            ->where('es_preventivo', false)
            ->update(['categoria' => 'accidente']);

        DB::table('maintenance_types')
            ->where(function ($query) {
                $query->whereNull('categoria')
                    ->orWhere('categoria', '');
            })
            ->update(['categoria' => 'preventivo_km']);
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_types') || !Schema::hasColumn('maintenance_types', 'categoria')) {
            return;
        }

        Schema::table('maintenance_types', function (Blueprint $table) {
            $table->dropColumn('categoria');
        });
    }
};
