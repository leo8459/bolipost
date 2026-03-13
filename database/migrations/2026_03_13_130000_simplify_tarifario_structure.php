<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tarifario', function (Blueprint $table) {
            $table->foreignId('destino_id')->nullable()->change();
            $table->foreignId('origen_id')->nullable()->change();
        });

        DB::transaction(function () {
            DB::table('tarifario')->update([
                'destino_id' => null,
                'origen_id' => null,
            ]);

            $duplicateIds = DB::table('tarifario as older')
                ->join('tarifario as newer', function ($join) {
                    $join->on('older.servicio_id', '=', 'newer.servicio_id')
                        ->on('older.peso_id', '=', 'newer.peso_id')
                        ->whereColumn('older.id', '<', 'newer.id');
                })
                ->distinct()
                ->pluck('older.id')
                ->all();

            if (!empty($duplicateIds)) {
                DB::table('tarifario')->whereIn('id', $duplicateIds)->delete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tarifario', function (Blueprint $table) {
            $table->foreignId('destino_id')->nullable(false)->change();
            $table->foreignId('origen_id')->nullable(false)->change();
        });
    }
};
