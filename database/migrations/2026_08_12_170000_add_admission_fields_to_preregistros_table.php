<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('preregistros', 'referencia')) {
            Schema::table('preregistros', function (Blueprint $table) {
                $table->string('referencia')->nullable()->after('direccion');
            });
        }

        if (! Schema::hasColumn('preregistros', 'observacion')) {
            Schema::table('preregistros', function (Blueprint $table) {
                $table->text('observacion')->nullable()->after('contenido');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('preregistros', 'referencia')) {
            Schema::table('preregistros', function (Blueprint $table) {
                $table->dropColumn('referencia');
            });
        }

        if (Schema::hasColumn('preregistros', 'observacion')) {
            Schema::table('preregistros', function (Blueprint $table) {
                $table->dropColumn('observacion');
            });
        }
    }
};
