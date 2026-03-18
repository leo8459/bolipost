<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fuel_invoice_details')) {
            Schema::create('fuel_invoice_details', function (Blueprint $table) {
                $table->id();
                $table->foreignId('fuel_invoice_id')->constrained('fuel_invoices')->cascadeOnDelete();
                $table->foreignId('fuel_log_id')->nullable()->constrained('fuel_logs')->nullOnDelete();
                $table->decimal('monto', 10, 2)->nullable();
                $table->text('detalle')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fuel_invoice_details');
    }
};

