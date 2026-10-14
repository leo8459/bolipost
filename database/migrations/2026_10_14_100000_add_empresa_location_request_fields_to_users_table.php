<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'empresa_ubicacion_solicitada_at')) {
                $table->timestamp('empresa_ubicacion_solicitada_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'empresa_ubicacion_respondida_at')) {
                $table->timestamp('empresa_ubicacion_respondida_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'empresa_ubicacion_capital')) {
                $table->boolean('empresa_ubicacion_capital')->default(false);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $columns = array_filter([
                Schema::hasColumn('users', 'empresa_ubicacion_solicitada_at') ? 'empresa_ubicacion_solicitada_at' : null,
                Schema::hasColumn('users', 'empresa_ubicacion_respondida_at') ? 'empresa_ubicacion_respondida_at' : null,
                Schema::hasColumn('users', 'empresa_ubicacion_capital') ? 'empresa_ubicacion_capital' : null,
            ]);

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
