<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('preregistros', 'codigo_preregistro')) {
            Schema::table('preregistros', function (Blueprint $table) {
                $table->string('codigo_preregistro', 20)->nullable()->after('id')->unique();
            });
        }

        DB::table('preregistros')
            ->whereNull('codigo_preregistro')
            ->orderBy('id')
            ->get(['id'])
            ->each(function ($row) {
                DB::table('preregistros')
                    ->where('id', $row->id)
                    ->update([
                        'codigo_preregistro' => 'PRE' . str_pad((string) $row->id, 8, '0', STR_PAD_LEFT),
                    ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('preregistros', 'codigo_preregistro')) {
            Schema::table('preregistros', function (Blueprint $table) {
                $table->dropUnique(['codigo_preregistro']);
                $table->dropColumn('codigo_preregistro');
            });
        }
    }
};
