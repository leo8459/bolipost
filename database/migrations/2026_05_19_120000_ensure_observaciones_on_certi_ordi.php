<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('paquetes_certi', 'observaciones')) {
            Schema::table('paquetes_certi', function (Blueprint $table) {
                $table->text('observaciones')->nullable()->after('aduana');
            });
        }

        if (! Schema::hasColumn('paquetes_ordi', 'observaciones')) {
            Schema::table('paquetes_ordi', function (Blueprint $table) {
                $table->text('observaciones')->nullable()->after('aduana');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('paquetes_certi', 'observaciones')) {
            Schema::table('paquetes_certi', function (Blueprint $table) {
                $table->dropColumn('observaciones');
            });
        }

        // paquetes_ordi has had this column in older installs; do not drop it on rollback.
    }
};
