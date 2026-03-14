<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('bitacoras') || !Schema::hasColumn('bitacoras', 'peso')) {
            return;
        }

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE bitacoras ALTER COLUMN peso DROP NOT NULL');
            DB::statement('ALTER TABLE bitacoras ALTER COLUMN peso DROP DEFAULT');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE bitacoras MODIFY peso DECIMAL(10,3) NULL');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE bitacoras ALTER COLUMN peso DECIMAL(10,3) NULL');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('bitacoras') || !Schema::hasColumn('bitacoras', 'peso')) {
            return;
        }

        DB::table('bitacoras')
            ->whereNull('peso')
            ->update(['peso' => 0]);

        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE bitacoras ALTER COLUMN peso SET DEFAULT 0');
            DB::statement('ALTER TABLE bitacoras ALTER COLUMN peso SET NOT NULL');
            return;
        }

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE bitacoras MODIFY peso DECIMAL(10,3) NOT NULL DEFAULT 0');
            return;
        }

        if ($driver === 'sqlsrv') {
            DB::statement('ALTER TABLE bitacoras ADD CONSTRAINT DF_bitacoras_peso DEFAULT 0 FOR peso');
            DB::statement('ALTER TABLE bitacoras ALTER COLUMN peso DECIMAL(10,3) NOT NULL');
        }
    }
};
