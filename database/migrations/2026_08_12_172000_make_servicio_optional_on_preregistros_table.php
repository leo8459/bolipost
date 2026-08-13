<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('preregistros', function (Blueprint $table) {
            $table->foreignId('servicio_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('preregistros', function (Blueprint $table) {
            $table->foreignId('servicio_id')->nullable(false)->change();
        });
    }
};
