<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('paquetes_int')) {
            return;
        }

        Schema::table('paquetes_int', function (Blueprint $table) {
            if (!Schema::hasColumn('paquetes_int', 'servicio_id')) {
                $table->unsignedBigInteger('servicio_id')->nullable()->after('codigo');
            }

            if (!Schema::hasColumn('paquetes_int', 'estado_id')) {
                $table->unsignedBigInteger('estado_id')->nullable()->after('servicio_id');
            }

            if (!Schema::hasColumn('paquetes_int', 'origen')) {
                $table->string('origen', 120)->nullable()->after('codigo');
            }

            if (!Schema::hasColumn('paquetes_int', 'precio')) {
                $table->decimal('precio', 10, 2)->nullable()->after('peso');
            }

            if (!Schema::hasColumn('paquetes_int', 'enviado_admision_at')) {
                $table->timestamp('enviado_admision_at')->nullable()->after('destino');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('paquetes_int')) {
            return;
        }

        Schema::table('paquetes_int', function (Blueprint $table) {
            if (Schema::hasColumn('paquetes_int', 'estado_id')) {
                $table->dropColumn('estado_id');
            }

            if (Schema::hasColumn('paquetes_int', 'servicio_id')) {
                $table->dropColumn('servicio_id');
            }

            if (Schema::hasColumn('paquetes_int', 'enviado_admision_at')) {
                $table->dropColumn('enviado_admision_at');
            }

            if (Schema::hasColumn('paquetes_int', 'precio')) {
                $table->dropColumn('precio');
            }

            if (Schema::hasColumn('paquetes_int', 'origen')) {
                $table->dropColumn('origen');
            }
        });
    }
};
