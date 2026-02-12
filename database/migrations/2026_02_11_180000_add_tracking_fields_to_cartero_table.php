<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cartero', function (Blueprint $table) {
            $table->unsignedInteger('intento')->default(0)->after('id_user');
            $table->string('recibido_por')->nullable()->after('intento');
            $table->text('descripcion')->nullable()->after('recibido_por');
        });
    }

    public function down(): void
    {
        Schema::table('cartero', function (Blueprint $table) {
            $table->dropColumn(['intento', 'recibido_por', 'descripcion']);
        });
    }
};

