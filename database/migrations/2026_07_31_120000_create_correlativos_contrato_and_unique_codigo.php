<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('correlativos_contrato', function (Blueprint $table) {
            $table->string('codigo_cliente')->primary();
            $table->unsignedBigInteger('ultimo_correlativo')->default(0);
            $table->timestamps();
        });

        if (Schema::hasTable('paquetes_contrato') && Schema::hasColumn('paquetes_contrato', 'codigo')) {
            $hayDuplicados = DB::table('paquetes_contrato')
                ->selectRaw('UPPER(TRIM(codigo)) AS codigo_normalizado')
                ->whereNotNull('codigo')
                ->groupByRaw('UPPER(TRIM(codigo))')
                ->havingRaw('COUNT(*) > 1')
                ->exists();

            if ($hayDuplicados && DB::getDriverName() === 'pgsql') {
                DB::unprepared(<<<'SQL'
                    CREATE OR REPLACE FUNCTION impedir_codigo_contrato_duplicado()
                    RETURNS trigger AS $$
                    BEGIN
                        PERFORM pg_advisory_xact_lock(hashtextextended(UPPER(TRIM(NEW.codigo)), 0));

                        IF EXISTS (
                            SELECT 1
                            FROM paquetes_contrato
                            WHERE UPPER(TRIM(codigo)) = UPPER(TRIM(NEW.codigo))
                              AND id <> COALESCE(NEW.id, 0)
                        ) THEN
                            RAISE EXCEPTION 'paquetes_contrato_codigo_unique: el codigo de contrato % ya existe.', NEW.codigo
                                USING ERRCODE = '23505',
                                      CONSTRAINT = 'paquetes_contrato_codigo_unique';
                        END IF;

                        RETURN NEW;
                    END;
                    $$ LANGUAGE plpgsql;

                    CREATE TRIGGER paquetes_contrato_codigo_unique_trigger
                    BEFORE INSERT OR UPDATE OF codigo ON paquetes_contrato
                    FOR EACH ROW
                    EXECUTE FUNCTION impedir_codigo_contrato_duplicado();
                    SQL);
            } elseif ($hayDuplicados) {
                throw new RuntimeException('No se puede activar la unicidad de paquetes_contrato.codigo porque existen codigos duplicados.');
            } elseif (DB::getDriverName() === 'pgsql') {
                DB::statement(
                    'CREATE UNIQUE INDEX paquetes_contrato_codigo_unique ON paquetes_contrato (UPPER(TRIM(codigo)))'
                );
            } else {
                Schema::table('paquetes_contrato', function (Blueprint $table) {
                    $table->unique('codigo', 'paquetes_contrato_codigo_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('paquetes_contrato') && Schema::hasColumn('paquetes_contrato', 'codigo')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::unprepared('DROP TRIGGER IF EXISTS paquetes_contrato_codigo_unique_trigger ON paquetes_contrato');
                DB::unprepared('DROP FUNCTION IF EXISTS impedir_codigo_contrato_duplicado()');
                DB::unprepared('DROP INDEX IF EXISTS paquetes_contrato_codigo_unique');
            } else {
                Schema::table('paquetes_contrato', function (Blueprint $table) {
                    $table->dropUnique('paquetes_contrato_codigo_unique');
                });
            }
        }

        Schema::dropIfExists('correlativos_contrato');
    }
};
