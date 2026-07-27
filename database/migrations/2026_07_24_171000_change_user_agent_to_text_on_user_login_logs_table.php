<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            DB::getDriverName() === 'pgsql'
            && Schema::hasTable('user_login_logs')
            && Schema::hasColumn('user_login_logs', 'user_agent')
        ) {
            DB::statement('ALTER TABLE user_login_logs ALTER COLUMN user_agent TYPE TEXT');
        }
    }

    public function down(): void
    {
        if (
            DB::getDriverName() === 'pgsql'
            && Schema::hasTable('user_login_logs')
            && Schema::hasColumn('user_login_logs', 'user_agent')
        ) {
            DB::statement('ALTER TABLE user_login_logs ALTER COLUMN user_agent TYPE VARCHAR(255)');
        }
    }
};
