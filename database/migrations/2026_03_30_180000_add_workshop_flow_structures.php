<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workshop_catalogs')) {
            Schema::create('workshop_catalogs', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 150);
                $table->string('tipo', 20)->default('Interno');
                $table->boolean('activo')->default(true);
                $table->timestamps();

                $table->index(['activo', 'tipo']);
            });
        }

        if (Schema::hasTable('vehicles') && !Schema::hasColumn('vehicles', 'operational_status')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->string('operational_status', 30)
                    ->default('Disponible')
                    ->after('activo');
            });
        }

        if (Schema::hasTable('workshops')) {
            Schema::table('workshops', function (Blueprint $table) {
                if (!Schema::hasColumn('workshops', 'workshop_catalog_id')) {
                    $table->foreignId('workshop_catalog_id')
                        ->nullable()
                        ->after('maintenance_log_id')
                        ->constrained('workshop_catalogs')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('workshops', 'maintenance_alert_id')) {
                    $table->foreignId('maintenance_alert_id')
                        ->nullable()
                        ->after('workshop_catalog_id')
                        ->constrained('maintenance_alerts')
                        ->nullOnDelete();
                }

                if (!Schema::hasColumn('workshops', 'order_number')) {
                    $table->string('order_number', 40)->nullable()->after('maintenance_alert_id');
                }

                if (!Schema::hasColumn('workshops', 'fecha_prometida_entrega')) {
                    $table->date('fecha_prometida_entrega')->nullable()->after('fecha_ingreso');
                }

                if (!Schema::hasColumn('workshops', 'fecha_listo')) {
                    $table->date('fecha_listo')->nullable()->after('fecha_prometida_entrega');
                }

                if (!Schema::hasColumn('workshops', 'observaciones_tecnicas')) {
                    $table->text('observaciones_tecnicas')->nullable()->after('pre_entrada_estado');
                }
            });

            DB::table('workshops')
                ->where('estado', 'Abierto')
                ->update(['estado' => 'Despachado']);

            DB::table('workshops')
                ->where('estado', 'Cerrado')
                ->update(['estado' => 'Entregado']);
        }

        if (Schema::hasTable('workshop_part_changes') && !Schema::hasColumn('workshop_part_changes', 'costo')) {
            Schema::table('workshop_part_changes', function (Blueprint $table) {
                $table->decimal('costo', 12, 2)->nullable()->after('descripcion');
            });
        }

        if (Schema::hasTable('workshops')) {
            $needsSeed = DB::table('workshop_catalogs')->count() === 0;
            if ($needsSeed) {
                DB::table('workshop_catalogs')->insert([
                    [
                        'nombre' => 'Taller Interno Central',
                        'tipo' => 'Interno',
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                    [
                        'nombre' => 'Taller Externo Convenio',
                        'tipo' => 'Externo',
                        'activo' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ]);
            }

            if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'operational_status')) {
                $vehicleIdsInWorkshop = DB::table('workshops')
                    ->whereIn('estado', ['Despachado', 'En diagnostico', 'En reparacion'])
                    ->pluck('vehicle_id')
                    ->filter()
                    ->unique()
                    ->values()
                    ->all();

                DB::table('vehicles')->update([
                    'operational_status' => 'Disponible',
                ]);

                if (!empty($vehicleIdsInWorkshop)) {
                    DB::table('vehicles')
                        ->whereIn('id', $vehicleIdsInWorkshop)
                        ->update([
                            'operational_status' => 'En Mantenimiento',
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('workshop_part_changes') && Schema::hasColumn('workshop_part_changes', 'costo')) {
            Schema::table('workshop_part_changes', function (Blueprint $table) {
                $table->dropColumn('costo');
            });
        }

        if (Schema::hasTable('workshops')) {
            Schema::table('workshops', function (Blueprint $table) {
                if (Schema::hasColumn('workshops', 'workshop_catalog_id')) {
                    $table->dropConstrainedForeignId('workshop_catalog_id');
                }
                if (Schema::hasColumn('workshops', 'maintenance_alert_id')) {
                    $table->dropConstrainedForeignId('maintenance_alert_id');
                }
                foreach (['order_number', 'fecha_prometida_entrega', 'fecha_listo', 'observaciones_tecnicas'] as $column) {
                    if (Schema::hasColumn('workshops', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }

        if (Schema::hasTable('vehicles') && Schema::hasColumn('vehicles', 'operational_status')) {
            Schema::table('vehicles', function (Blueprint $table) {
                $table->dropColumn('operational_status');
            });
        }

        if (Schema::hasTable('workshop_catalogs')) {
            Schema::dropIfExists('workshop_catalogs');
        }
    }
};
