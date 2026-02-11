<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $aperturaId = DB::table('estados')
            ->where('nombre_estado', 'APERTURA')
            ->value('id');

        if (!$aperturaId) {
            $aperturaId = 11;
        }

        Schema::table('despacho', function (Blueprint $table) {
            $table->foreignId('fk_estado')->nullable()->after('departamento');
        });

        DB::statement("
            UPDATE despacho d
            SET fk_estado = e.id
            FROM estados e
            WHERE UPPER(TRIM(COALESCE(d.estado, ''))) = UPPER(TRIM(e.nombre_estado))
        ");

        DB::statement("
            UPDATE despacho
            SET fk_estado = CAST(estado AS BIGINT)
            WHERE fk_estado IS NULL
              AND estado ~ '^[0-9]+$'
        ");

        DB::table('despacho')
            ->whereNull('fk_estado')
            ->update(['fk_estado' => $aperturaId]);

        Schema::table('despacho', function (Blueprint $table) {
            $table->foreign('fk_estado')->references('id')->on('estados');
        });

        Schema::table('saca', function (Blueprint $table) {
            $table->foreignId('fk_estado')->nullable()->after('identificador');
        });

        DB::statement("
            UPDATE saca s
            SET fk_estado = e.id
            FROM estados e
            WHERE UPPER(TRIM(COALESCE(s.estado, ''))) = UPPER(TRIM(e.nombre_estado))
        ");

        DB::statement("
            UPDATE saca
            SET fk_estado = CAST(estado AS BIGINT)
            WHERE fk_estado IS NULL
              AND estado ~ '^[0-9]+$'
        ");

        DB::table('saca')
            ->whereNull('fk_estado')
            ->update(['fk_estado' => $aperturaId]);

        Schema::table('saca', function (Blueprint $table) {
            $table->foreign('fk_estado')->references('id')->on('estados');
        });

        Schema::table('despacho', function (Blueprint $table) {
            $table->dropColumn('estado');
        });

        Schema::table('saca', function (Blueprint $table) {
            $table->dropColumn('estado');
        });
    }

    public function down(): void
    {
        Schema::table('despacho', function (Blueprint $table) {
            $table->string('estado')->nullable()->after('departamento');
        });

        DB::statement("
            UPDATE despacho d
            SET estado = e.nombre_estado
            FROM estados e
            WHERE d.fk_estado = e.id
        ");

        Schema::table('saca', function (Blueprint $table) {
            $table->string('estado')->nullable()->after('identificador');
        });

        DB::statement("
            UPDATE saca s
            SET estado = e.nombre_estado
            FROM estados e
            WHERE s.fk_estado = e.id
        ");

        Schema::table('despacho', function (Blueprint $table) {
            $table->dropForeign(['fk_estado']);
            $table->dropColumn('fk_estado');
        });

        Schema::table('saca', function (Blueprint $table) {
            $table->dropForeign(['fk_estado']);
            $table->dropColumn('fk_estado');
        });
    }
};
