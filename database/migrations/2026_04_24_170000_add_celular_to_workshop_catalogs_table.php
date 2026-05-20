<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_catalogs') && !Schema::hasColumn('workshop_catalogs', 'celular')) {
            Schema::table('workshop_catalogs', function (Blueprint $table): void {
                $table->string('celular', 30)->nullable()->after('tipo');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workshop_catalogs') && Schema::hasColumn('workshop_catalogs', 'celular')) {
            Schema::table('workshop_catalogs', function (Blueprint $table): void {
                $table->dropColumn('celular');
            });
        }
    }
};
