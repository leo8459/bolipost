<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vehicle_classes')) {
            Schema::table('vehicle_classes', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicle_classes', 'maintenance_form_type')) {
                    $table->string('maintenance_form_type', 20)->nullable()->after('nombre');
                }
            });
        }

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'maintenance_form_type')) {
                    $table->string('maintenance_form_type', 20)->nullable()->after('vehicle_class_id');
                }
            });
        }

        $this->backfillVehicleClasses();
        $this->backfillVehicles();
    }

    public function down(): void
    {
        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (Schema::hasColumn('vehicles', 'maintenance_form_type')) {
                    $table->dropColumn('maintenance_form_type');
                }
            });
        }

        if (Schema::hasTable('vehicle_classes')) {
            Schema::table('vehicle_classes', function (Blueprint $table) {
                if (Schema::hasColumn('vehicle_classes', 'maintenance_form_type')) {
                    $table->dropColumn('maintenance_form_type');
                }
            });
        }
    }

    private function backfillVehicleClasses(): void
    {
        if (!Schema::hasTable('vehicle_classes') || !Schema::hasColumn('vehicle_classes', 'maintenance_form_type')) {
            return;
        }

        $rows = DB::table('vehicle_classes')->select(['id', 'nombre', 'modelo'])->get();
        foreach ($rows as $row) {
            DB::table('vehicle_classes')
                ->where('id', $row->id)
                ->update([
                    'maintenance_form_type' => $this->inferFormType(
                        (string) ($row->nombre ?? ''),
                        (string) ($row->modelo ?? '')
                    ),
                ]);
        }
    }

    private function backfillVehicles(): void
    {
        if (!Schema::hasTable('vehicles') || !Schema::hasColumn('vehicles', 'maintenance_form_type')) {
            return;
        }

        $rows = DB::table('vehicles as v')
            ->leftJoin('vehicle_classes as vc', 'vc.id', '=', 'v.vehicle_class_id')
            ->select([
                'v.id',
                'v.modelo as vehicle_modelo',
                'v.tipo_combustible',
                'vc.maintenance_form_type as class_form_type',
                'vc.nombre as class_nombre',
                'vc.modelo as class_modelo',
            ])
            ->get();

        foreach ($rows as $row) {
            $type = $row->class_form_type
                ?: $this->inferFormType(
                    (string) ($row->class_nombre ?? ''),
                    (string) ($row->class_modelo ?? ''),
                    (string) ($row->vehicle_modelo ?? ''),
                    (string) ($row->tipo_combustible ?? '')
                );

            DB::table('vehicles')
                ->where('id', $row->id)
                ->update(['maintenance_form_type' => $type]);
        }
    }

    private function inferFormType(string ...$sources): string
    {
        $haystack = mb_strtolower(trim(implode(' ', $sources)));
        foreach (['moto', 'motocic', 'scooter', 'cuatrimoto', 'cuadri', 'quadratrack', 'atv'] as $pattern) {
            if ($pattern !== '' && str_contains($haystack, $pattern)) {
                return 'moto';
            }
        }

        return 'vehiculo';
    }
};
