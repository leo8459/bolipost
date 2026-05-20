<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('workshops')) {
            Schema::table('workshops', function (Blueprint $table) {
                if (!Schema::hasColumn('workshops', 'workflow_kind')) {
                    $table->string('workflow_kind', 20)->default('Leve')->after('estado');
                }
                if (!Schema::hasColumn('workshops', 'approval_required')) {
                    $table->boolean('approval_required')->default(false)->after('workflow_kind');
                }
                if (!Schema::hasColumn('workshops', 'fixed_catalog_cost')) {
                    $table->decimal('fixed_catalog_cost', 10, 2)->nullable()->after('approval_required');
                }
                if (!Schema::hasColumn('workshops', 'labor_cost')) {
                    $table->decimal('labor_cost', 10, 2)->nullable()->after('fixed_catalog_cost');
                }
                if (!Schema::hasColumn('workshops', 'additional_cost')) {
                    $table->decimal('additional_cost', 10, 2)->nullable()->after('labor_cost');
                }
                if (!Schema::hasColumn('workshops', 'total_cost')) {
                    $table->decimal('total_cost', 10, 2)->nullable()->after('additional_cost');
                }
                if (!Schema::hasColumn('workshops', 'diagnosis_requested_at')) {
                    $table->timestamp('diagnosis_requested_at')->nullable()->after('fecha_salida');
                }
                if (!Schema::hasColumn('workshops', 'fecha_aprobacion')) {
                    $table->timestamp('fecha_aprobacion')->nullable()->after('diagnosis_requested_at');
                }
                if (!Schema::hasColumn('workshops', 'fecha_cierre')) {
                    $table->timestamp('fecha_cierre')->nullable()->after('fecha_aprobacion');
                }
                if (!Schema::hasColumn('workshops', 'dispatched_by_user_id')) {
                    $table->foreignId('dispatched_by_user_id')->nullable()->after('fecha_cierre')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('workshops', 'diagnosed_by_user_id')) {
                    $table->foreignId('diagnosed_by_user_id')->nullable()->after('dispatched_by_user_id')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('workshops', 'approved_by_user_id')) {
                    $table->foreignId('approved_by_user_id')->nullable()->after('diagnosed_by_user_id')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('workshops', 'closed_by_user_id')) {
                    $table->foreignId('closed_by_user_id')->nullable()->after('approved_by_user_id')->constrained('users')->nullOnDelete();
                }
                if (!Schema::hasColumn('workshops', 'reassigned_from_workshop_catalog_id')) {
                    $table->foreignId('reassigned_from_workshop_catalog_id')->nullable()->after('closed_by_user_id')->constrained('workshop_catalogs')->nullOnDelete();
                }
                if (!Schema::hasColumn('workshops', 'rejection_reason')) {
                    $table->text('rejection_reason')->nullable()->after('reassigned_from_workshop_catalog_id');
                }
                if (!Schema::hasColumn('workshops', 'cancellation_reason')) {
                    $table->text('cancellation_reason')->nullable()->after('rejection_reason');
                }
                if (!Schema::hasColumn('workshops', 'reception_photo_path')) {
                    $table->string('reception_photo_path')->nullable()->after('cancellation_reason');
                }
                if (!Schema::hasColumn('workshops', 'damage_photo_path')) {
                    $table->string('damage_photo_path')->nullable()->after('reception_photo_path');
                }
                if (!Schema::hasColumn('workshops', 'invoice_file_path')) {
                    $table->string('invoice_file_path')->nullable()->after('damage_photo_path');
                }
                if (!Schema::hasColumn('workshops', 'receipt_file_path')) {
                    $table->string('receipt_file_path')->nullable()->after('invoice_file_path');
                }
            });
        }

        if (Schema::hasTable('driver_incentive_reports')) {
            Schema::table('driver_incentive_reports', function (Blueprint $table) {
                if (!Schema::hasColumn('driver_incentive_reports', 'discountable_events')) {
                    $table->unsignedInteger('discountable_events')->default(0)->after('preventive_requests');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('driver_incentive_reports')) {
            Schema::table('driver_incentive_reports', function (Blueprint $table) {
                if (Schema::hasColumn('driver_incentive_reports', 'discountable_events')) {
                    $table->dropColumn('discountable_events');
                }
            });
        }

        if (Schema::hasTable('workshops')) {
            Schema::table('workshops', function (Blueprint $table) {
                foreach ([
                    'dispatched_by_user_id',
                    'diagnosed_by_user_id',
                    'approved_by_user_id',
                    'closed_by_user_id',
                    'reassigned_from_workshop_catalog_id',
                ] as $foreignColumn) {
                    if (Schema::hasColumn('workshops', $foreignColumn)) {
                        $table->dropConstrainedForeignId($foreignColumn);
                    }
                }

                foreach ([
                    'workflow_kind',
                    'approval_required',
                    'fixed_catalog_cost',
                    'labor_cost',
                    'additional_cost',
                    'total_cost',
                    'diagnosis_requested_at',
                    'fecha_aprobacion',
                    'fecha_cierre',
                    'rejection_reason',
                    'cancellation_reason',
                    'reception_photo_path',
                    'damage_photo_path',
                    'invoice_file_path',
                    'receipt_file_path',
                ] as $column) {
                    if (Schema::hasColumn('workshops', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
