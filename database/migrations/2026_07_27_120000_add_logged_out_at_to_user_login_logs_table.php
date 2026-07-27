<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            Schema::hasTable('user_login_logs')
            && ! Schema::hasColumn('user_login_logs', 'logged_out_at')
        ) {
            Schema::table('user_login_logs', function (Blueprint $table) {
                $table->timestamp('logged_out_at')->nullable()->after('logged_in_at');
                $table->index(['user_id', 'logged_out_at']);
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('user_login_logs')
            && Schema::hasColumn('user_login_logs', 'logged_out_at')
        ) {
            Schema::table('user_login_logs', function (Blueprint $table) {
                $table->dropIndex(['user_id', 'logged_out_at']);
                $table->dropColumn('logged_out_at');
            });
        }
    }
};
