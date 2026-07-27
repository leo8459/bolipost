<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            ! Schema::hasTable('user_login_logs')
            || ! Schema::hasColumn('user_login_logs', 'logged_out_at')
        ) {
            return;
        }

        $logsByUser = DB::table('user_login_logs')
            ->select('id', 'user_id', 'logged_in_at')
            ->whereNotNull('user_id')
            ->whereNull('logged_out_at')
            ->orderBy('user_id')
            ->orderBy('logged_in_at')
            ->orderBy('id')
            ->get()
            ->groupBy('user_id');

        foreach ($logsByUser as $logs) {
            $previousLog = null;

            foreach ($logs as $log) {
                if ($previousLog !== null) {
                    DB::table('user_login_logs')
                        ->where('id', $previousLog->id)
                        ->whereNull('logged_out_at')
                        ->update(['logged_out_at' => $log->logged_in_at]);
                }

                $previousLog = $log;
            }
        }
    }

    public function down(): void
    {
        // No se revierte el backfill para no volver a abrir historiales ya cerrados.
    }
};
