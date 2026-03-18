<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicle_brands')) {
            Schema::create('vehicle_brands', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->string('pais_origen')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('vehicle_classes')) {
            Schema::create('vehicle_classes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('marca_id')->nullable();
                $table->string('modelo', 50);
                $table->integer('anio');
                $table->string('nombre', 180);
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique(['marca_id', 'modelo', 'anio'], 'vehicle_classes_unique_triplet');
            });
        }

        if (Schema::hasTable('vehicles')) {
            Schema::table('vehicles', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicles', 'marca_id')) {
                    $table->unsignedBigInteger('marca_id')->nullable()->index();
                }
                if (!Schema::hasColumn('vehicles', 'vehicle_class_id')) {
                    $table->unsignedBigInteger('vehicle_class_id')->nullable()->index();
                }
                if (!Schema::hasColumn('vehicles', 'kilometraje')) {
                    $table->decimal('kilometraje', 10, 2)->nullable();
                }
            });
        }

        if (Schema::hasTable('drivers')) {
            Schema::table('drivers', function (Blueprint $table) {
                if (!Schema::hasColumn('drivers', 'memorandum_path')) {
                    $table->string('memorandum_path')->nullable()->after('email');
                }
            });
        }

        if (!Schema::hasTable('maintenance_types')) {
            Schema::create('maintenance_types', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('vehicle_class_id')->nullable()->index();
                $table->string('nombre', 255);
                $table->integer('cada_km')->nullable();
                $table->integer('intervalo_km')->nullable();
                $table->integer('intervalo_km_init')->nullable();
                $table->integer('intervalo_km_fh')->nullable();
                $table->integer('km_alerta_previa')->default(15);
                $table->text('descripcion')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::table('maintenance_types', function (Blueprint $table) {
                if (!Schema::hasColumn('maintenance_types', 'vehicle_class_id')) {
                    $table->unsignedBigInteger('vehicle_class_id')->nullable()->index();
                }
                if (!Schema::hasColumn('maintenance_types', 'cada_km')) {
                    $table->integer('cada_km')->nullable();
                }
                if (!Schema::hasColumn('maintenance_types', 'intervalo_km_init')) {
                    $table->integer('intervalo_km_init')->nullable();
                }
                if (!Schema::hasColumn('maintenance_types', 'intervalo_km_fh')) {
                    $table->integer('intervalo_km_fh')->nullable();
                }
                if (!Schema::hasColumn('maintenance_types', 'km_alerta_previa')) {
                    $table->integer('km_alerta_previa')->default(15);
                }
            });
        }

        if (!Schema::hasTable('vehicle_assignments')) {
            Schema::create('vehicle_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->onDelete('cascade');
                $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('cascade');
                $table->string('tipo_asignacion', 100)->nullable();
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        } else {
            Schema::table('vehicle_assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicle_assignments', 'created_at')) {
                    $table->timestamps();
                }
            });
        }

        if (!Schema::hasTable('maintenance_appointments')) {
            Schema::create('maintenance_appointments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('cascade');
                $table->foreignId('driver_id')->nullable()->constrained('drivers')->onDelete('set null');
                $table->foreignId('tipo_mantenimiento_id')->nullable()->constrained('maintenance_types')->onDelete('set null');
                $table->dateTime('fecha_programada')->nullable();
                $table->boolean('es_accidente')->default(false);
                $table->text('descripcion_problema')->nullable();
                $table->string('estado', 50)->default('Pendiente');
                $table->timestamps();
            });
        }

        if (Schema::hasTable('maintenance_logs')) {
            Schema::table('maintenance_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('maintenance_logs', 'maintenance_type_id')) {
                    $table->foreignId('maintenance_type_id')->nullable()->constrained('maintenance_types')->nullOnDelete();
                }
                if (!Schema::hasColumn('maintenance_logs', 'proximo_kilometraje')) {
                    $table->decimal('proximo_kilometraje', 10, 2)->nullable()->index();
                }
            });
        }

        if (!Schema::hasTable('maintenance_work_orders')) {
            Schema::create('maintenance_work_orders', function (Blueprint $table) {
                $table->id();
                $table->string('codigo', 30)->unique();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('maintenance_type_id')->nullable()->constrained('maintenance_types')->nullOnDelete();
                $table->string('status', 20)->default('Pendiente');
                $table->text('descripcion')->nullable();
                $table->decimal('costo_estimado', 12, 2)->nullable();
                $table->decimal('costo_final', 12, 2)->nullable();
                $table->string('factura_final_path')->nullable();
                $table->dateTime('fecha_solicitud')->nullable();
                $table->dateTime('fecha_aprobacion')->nullable();
                $table->dateTime('fecha_inicio')->nullable();
                $table->dateTime('fecha_completado')->nullable();
                $table->dateTime('fecha_cierre')->nullable();
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('closed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['vehicle_id', 'status']);
            });
        }

        if (!Schema::hasTable('maintenance_alerts')) {
            Schema::create('maintenance_alerts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vehicle_id')->constrained('vehicles')->cascadeOnDelete();
                $table->foreignId('maintenance_type_id')->nullable()->constrained('maintenance_types')->nullOnDelete();
                $table->foreignId('maintenance_appointment_id')->nullable()->constrained('maintenance_appointments')->nullOnDelete();
                $table->string('tipo', 30);
                $table->string('mensaje', 255);
                $table->boolean('leida')->default(false);
                $table->string('status', 20)->default('Activa')->index();
                $table->dateTime('fecha_resolucion')->nullable();
                $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('ot_id')->nullable()->constrained('maintenance_work_orders')->nullOnDelete();
                $table->decimal('kilometraje_actual', 10, 2)->nullable();
                $table->decimal('kilometraje_objetivo', 10, 2)->nullable();
                $table->decimal('faltante_km', 10, 2)->nullable();
                $table->timestamps();
                $table->index(['vehicle_id', 'tipo', 'leida']);
            });
        }

        if (Schema::hasTable('vehicle_log')) {
            Schema::table('vehicle_log', function (Blueprint $table) {
                if (!Schema::hasColumn('vehicle_log', 'ruta_json')) {
                    $table->longText('ruta_json')->nullable()->after('logitud_destino');
                }
            });
        }
    }

    public function down(): void
    {
        // Migration de integracion: no hace rollback destructivo.
    }
};
