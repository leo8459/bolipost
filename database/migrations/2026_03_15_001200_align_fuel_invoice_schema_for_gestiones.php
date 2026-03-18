<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('fuel_invoices')) {
            Schema::table('fuel_invoices', function (Blueprint $table) {
                if (!Schema::hasColumn('fuel_invoices', 'numero_factura')) {
                    $table->string('numero_factura')->nullable()->index();
                }
                if (!Schema::hasColumn('fuel_invoices', 'nombre_cliente')) {
                    $table->string('nombre_cliente')->nullable();
                }
            });

            if (Schema::hasColumn('fuel_invoices', 'numero') && Schema::hasColumn('fuel_invoices', 'numero_factura')) {
                DB::table('fuel_invoices')
                    ->whereNull('numero_factura')
                    ->update(['numero_factura' => DB::raw('numero')]);
            }
        }

        if (Schema::hasTable('fuel_invoice_details')) {
            Schema::table('fuel_invoice_details', function (Blueprint $table) {
                if (!Schema::hasColumn('fuel_invoice_details', 'gas_station_id')) {
                    $table->foreignId('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete();
                }
                if (!Schema::hasColumn('fuel_invoice_details', 'cantidad')) {
                    $table->decimal('cantidad', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('fuel_invoice_details', 'precio_unitario')) {
                    $table->decimal('precio_unitario', 10, 2)->nullable();
                }
                if (!Schema::hasColumn('fuel_invoice_details', 'subtotal')) {
                    $table->decimal('subtotal', 10, 2)->nullable();
                }
            });

            if (Schema::hasColumn('fuel_invoice_details', 'monto')) {
                if (Schema::hasColumn('fuel_invoice_details', 'subtotal')) {
                    DB::table('fuel_invoice_details')
                        ->whereNull('subtotal')
                        ->update(['subtotal' => DB::raw('monto')]);
                }
                if (Schema::hasColumn('fuel_invoice_details', 'cantidad') && Schema::hasColumn('fuel_invoice_details', 'precio_unitario')) {
                    DB::table('fuel_invoice_details')
                        ->whereNull('cantidad')
                        ->update(['cantidad' => 1]);
                    DB::table('fuel_invoice_details')
                        ->whereNull('precio_unitario')
                        ->update(['precio_unitario' => DB::raw('COALESCE(subtotal, 0)')]);
                }
            }
        }
    }

    public function down(): void
    {
        // Migration de integracion: sin rollback destructivo.
    }
};
