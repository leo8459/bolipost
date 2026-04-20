<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('maintenance_appointments', function (Blueprint $table): void {
            if (!Schema::hasColumn('maintenance_appointments', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('estado');
            }

            if (!Schema::hasColumn('maintenance_appointments', 'approved_by_user_id')) {
                $table->foreignId('approved_by_user_id')
                    ->nullable()
                    ->after('approved_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_appointments', function (Blueprint $table): void {
            if (Schema::hasColumn('maintenance_appointments', 'approved_by_user_id')) {
                $table->dropConstrainedForeignId('approved_by_user_id');
            }

            if (Schema::hasColumn('maintenance_appointments', 'approved_at')) {
                $table->dropColumn('approved_at');
            }
        });
    }
};
