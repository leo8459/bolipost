<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->string('factura_cuf')->nullable();
            $table->string('factura_numero', 100)->nullable();
            $table->string('factura_pdf_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('conciliaciones_empresa', function (Blueprint $table): void {
            $table->dropColumn(['factura_cuf', 'factura_numero', 'factura_pdf_path']);
        });
    }
};
