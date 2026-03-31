<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('maintenance_types')) {
            return;
        }

        $sourceTypes = DB::table('maintenance_types')
            ->where(function ($query) {
                $query->whereNull('maintenance_form_type')
                    ->orWhere('maintenance_form_type', 'vehiculo');
            })
            ->whereNull('vehicle_class_id')
            ->get();

        foreach ($sourceTypes as $source) {
            $exists = DB::table('maintenance_types')
                ->whereRaw('LOWER(nombre) = ?', [mb_strtolower((string) $source->nombre)])
                ->where('maintenance_form_type', 'moto')
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('maintenance_types')->insert([
                'nombre' => $source->nombre,
                'vehicle_class_id' => null,
                'maintenance_form_type' => 'moto',
                'es_preventivo' => $source->es_preventivo ?? false,
                'cada_km' => $source->cada_km ?? null,
                'intervalo_km' => $source->intervalo_km ?? null,
                'intervalo_km_init' => $source->intervalo_km_init ?? null,
                'intervalo_km_fh' => $source->intervalo_km_fh ?? null,
                'km_alerta_previa' => $source->km_alerta_previa ?? null,
                'descripcion' => $source->descripcion ?? null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('maintenance_types')) {
            return;
        }

        DB::table('maintenance_types')
            ->where('maintenance_form_type', 'moto')
            ->whereIn('nombre', ['Cambio de aceites', 'Revisión de Seguridad'])
            ->delete();
    }
};
