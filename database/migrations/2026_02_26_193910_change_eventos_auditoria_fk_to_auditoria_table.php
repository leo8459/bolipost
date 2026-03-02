<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('eventos_auditoria', function (Blueprint $table) {
            $table->dropForeign(['auditoria_id']);
            $table->foreign('auditoria_id')
                ->references('id')
                ->on('auditoria')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('eventos_auditoria', function (Blueprint $table) {
            $table->dropForeign(['auditoria_id']);
            $table->foreign('auditoria_id')
                ->references('id')
                ->on('eventos')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
        });
    }
};
