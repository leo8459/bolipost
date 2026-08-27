<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('alertas_empresa', function (Blueprint $table): void {
            $table->timestamp('aprobada_at')->nullable()->after('publicada_at');
            $table->foreignId('aprobada_por')->nullable()->after('aprobada_at')
                ->constrained('users')->nullOnDelete();
        });

        // Las alertas anteriores a este flujo ya estaban visibles y deben conservarse publicadas.
        DB::table('alertas_empresa')
            ->whereNull('aprobada_at')
            ->update(['aprobada_at' => DB::raw('publicada_at')]);
    }

    public function down(): void
    {
        Schema::table('alertas_empresa', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('aprobada_por');
            $table->dropColumn('aprobada_at');
        });
    }
};
