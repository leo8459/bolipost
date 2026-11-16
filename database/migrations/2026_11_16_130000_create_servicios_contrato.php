<?php

use App\Models\ServicioContrato;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servicios_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->unsignedSmallInteger('anio')->nullable()->index();
            $table->timestamps();
            $table->unique(['nombre', 'anio']);
        });
        DB::statement('CREATE UNIQUE INDEX servicios_contrato_sin_anio_unico ON servicios_contrato (nombre) WHERE anio IS NULL');
        Schema::table('tarifa_contrato', function (Blueprint $table) {
            $table->foreignId('servicio_contrato_id')->nullable()->constrained('servicios_contrato')->restrictOnDelete();
        });
        DB::transaction(function () {
            DB::table('tarifa_contrato as tarifa')->join('tarifarios_contrato as tarifario', 'tarifario.id', '=', 'tarifa.tarifario_contrato_id')
                ->select(['tarifa.id', 'tarifa.servicio', 'tarifario.anio'])->orderBy('tarifa.id')->chunkById(200, function ($rows) {
                    foreach ($rows as $row) {
                        $service = ServicioContrato::asegurar((string) $row->servicio, $row->anio);
                        // Keep historical text, IDs and prices unchanged; add only the relationship.
                        DB::table('tarifa_contrato')->where('id', $row->id)->update(['servicio_contrato_id' => $service->id]);
                    }
                }, 'tarifa.id', 'id');
        });
    }

    public function down(): void
    {
        Schema::table('tarifa_contrato', fn (Blueprint $table) => $table->dropConstrainedForeignId('servicio_contrato_id'));
        Schema::dropIfExists('servicios_contrato');
    }
};
