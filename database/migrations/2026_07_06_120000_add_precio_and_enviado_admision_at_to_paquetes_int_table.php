<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->decimal('precio', 10, 2)->nullable()->after('peso');
            $table->timestamp('enviado_admision_at')->nullable()->after('destino');
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_int', function (Blueprint $table) {
            $table->dropColumn(['precio', 'enviado_admision_at']);
        });
    }
};
