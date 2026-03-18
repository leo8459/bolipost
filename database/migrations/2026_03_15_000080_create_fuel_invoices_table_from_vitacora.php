<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_invoices')) {
            Schema::create('fuel_invoices', function (Blueprint $table) {
                $table->id();
                $table->string('numero')->unique();
                $table->date('fecha_emision')->nullable();
                $table->foreignId('gas_station_id')->nullable()->constrained('gas_stations')->nullOnDelete();
                $table->decimal('monto_total', 10, 2)->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_invoices');
    }
};

