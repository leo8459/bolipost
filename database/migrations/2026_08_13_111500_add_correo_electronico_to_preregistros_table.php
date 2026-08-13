<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('preregistros', 'correo_electronico')) {
            Schema::table('preregistros', function (Blueprint $table) {
                $table->string('correo_electronico')->nullable()->after('telefono_remitente');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('preregistros', 'correo_electronico')) {
            Schema::table('preregistros', function (Blueprint $table) {
                $table->dropColumn('correo_electronico');
            });
        }
    }
};
