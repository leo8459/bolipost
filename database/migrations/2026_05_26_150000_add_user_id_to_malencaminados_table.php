<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('malencaminados', function (Blueprint $table) {
            if (! Schema::hasColumn('malencaminados', 'user_id')) {
                $table->foreignId('user_id')
                    ->nullable()
                    ->after('malencaminamiento')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('malencaminados', function (Blueprint $table) {
            if (Schema::hasColumn('malencaminados', 'user_id')) {
                $table->dropConstrainedForeignId('user_id');
            }
        });
    }
};
