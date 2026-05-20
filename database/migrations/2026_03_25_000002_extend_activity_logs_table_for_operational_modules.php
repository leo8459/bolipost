<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('activity_logs', 'model')) {
                $table->string('model', 120)->nullable()->after('action');
            }
            if (!Schema::hasColumn('activity_logs', 'record_id')) {
                $table->unsignedBigInteger('record_id')->nullable()->after('module');
            }
            if (!Schema::hasColumn('activity_logs', 'changes_json')) {
                $table->json('changes_json')->nullable()->after('record_id');
            }
            if (!Schema::hasColumn('activity_logs', 'fecha')) {
                $table->timestamp('fecha')->nullable()->after('user_agent');
            }
        });

        if (Schema::hasColumn('activity_logs', 'fecha') && Schema::hasColumn('activity_logs', 'created_at')) {
            DB::table('activity_logs')
                ->whereNull('fecha')
                ->update(['fecha' => DB::raw('created_at')]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('activity_logs')) {
            return;
        }

        Schema::table('activity_logs', function (Blueprint $table) {
            foreach (['fecha', 'changes_json', 'record_id', 'model'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
