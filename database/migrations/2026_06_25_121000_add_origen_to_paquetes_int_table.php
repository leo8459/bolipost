<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->string('origen', 120)->nullable()->after('codigo');
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->dropColumn('origen');
        });
    }
};
