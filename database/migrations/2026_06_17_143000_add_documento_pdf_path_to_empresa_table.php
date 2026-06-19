<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->string('documento_pdf_path')->nullable()->after('presupuesto');
        });
    }

    public function down(): void
    {
        Schema::table('empresa', function (Blueprint $table) {
            $table->dropColumn('documento_pdf_path');
        });
    }
};
