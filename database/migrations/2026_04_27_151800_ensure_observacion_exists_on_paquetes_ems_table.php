<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('paquetes_ems')) {
            return;
        }

        if (! Schema::hasColumn('paquetes_ems', 'observacion')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->text('observacion')->nullable()->after('imagen');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('paquetes_ems')) {
            return;
        }

        if (Schema::hasColumn('paquetes_ems', 'observacion')) {
            Schema::table('paquetes_ems', function (Blueprint $table) {
                $table->dropColumn('observacion');
            });
        }
    }
};
