<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshop_catalogs') && !Schema::hasColumn('workshop_catalogs', 'user_id')) {
            Schema::table('workshop_catalogs', function (Blueprint $table) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('tipo')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workshop_catalogs') && Schema::hasColumn('workshop_catalogs', 'user_id')) {
            Schema::table('workshop_catalogs', function (Blueprint $table) {
                $table->dropConstrainedForeignId('user_id');
            });
        }
    }
};
