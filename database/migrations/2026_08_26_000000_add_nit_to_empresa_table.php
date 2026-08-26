<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresa', function (Blueprint $table): void {
            $table->string('nit', 32)->nullable()->after('codigo_cliente');
        });

        Schema::table('empresa_historiales', function (Blueprint $table): void {
            $table->string('nit', 32)->nullable()->after('codigo_cliente');
        });
    }

    public function down(): void
    {
        Schema::table('empresa_historiales', function (Blueprint $table): void {
            $table->dropColumn('nit');
        });

        Schema::table('empresa', function (Blueprint $table): void {
            $table->dropColumn('nit');
        });
    }
};
