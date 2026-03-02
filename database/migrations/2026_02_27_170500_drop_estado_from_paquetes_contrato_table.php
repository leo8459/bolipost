<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('paquetes_contrato', 'estado')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->dropColumn('estado');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasColumn('paquetes_contrato', 'estado')) {
            Schema::table('paquetes_contrato', function (Blueprint $table) {
                $table->string('estado')->nullable();
            });
        }

        DB::table('paquetes_contrato')
            ->whereNull('estado')
            ->update(['estado' => 'SOLICITUD']);
    }
};

