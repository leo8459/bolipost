<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('cartero', 'imagen_devolucion')) {
            Schema::table('cartero', function (Blueprint $table) {
                $table->string('imagen_devolucion')->nullable()->after('imagen');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('cartero', 'imagen_devolucion')) {
            Schema::table('cartero', function (Blueprint $table) {
                $table->dropColumn('imagen_devolucion');
            });
        }
    }
};
