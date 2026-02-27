<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_contrato', function (Blueprint $table) {
            $table->foreignId('estados_id')
                ->nullable()
                ->after('estado')
                ->constrained('estados')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });

        $estadoSolicitudId = DB::table('estados')
            ->whereRaw('trim(upper(nombre_estado)) = ?', ['SOLICITUD'])
            ->value('id');

        DB::table('paquetes_contrato')->update([
            'estado' => 'SOLICITUD',
        ]);

        if (!empty($estadoSolicitudId)) {
            DB::table('paquetes_contrato')
                ->whereNull('estados_id')
                ->update([
                    'estados_id' => (int) $estadoSolicitudId,
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('paquetes_contrato', function (Blueprint $table) {
            $table->dropForeign(['estados_id']);
            $table->dropColumn('estados_id');
        });
    }
};
