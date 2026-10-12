<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifario_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarifario_padre_id')->constrained('tarifario_padre')->restrictOnDelete();
            $table->unsignedInteger('numero_version');
            $table->string('codigo', 80)->unique();
            $table->string('estado', 20)->default('PUBLICADA');
            $table->unsignedBigInteger('publicada_por')->nullable();
            $table->timestamp('publicada_en');
            $table->unsignedInteger('cantidad_items')->default(0);
            $table->string('checksum', 64);
            $table->timestamps();

            $table->unique(['tarifario_padre_id', 'numero_version'], 'tarifario_version_padre_numero_unique');
            $table->index(['tarifario_padre_id', 'publicada_en']);
        });

        Schema::create('tarifario_version_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarifario_version_id')->constrained('tarifario_versions')->cascadeOnDelete();
            $table->unsignedBigInteger('source_tarifario_id');
            $table->unsignedBigInteger('servicio_id');
            $table->unsignedBigInteger('peso_id')->nullable();
            $table->decimal('peso_inicial', 10, 3);
            $table->decimal('peso_final', 10, 3);
            $table->decimal('precio', 12, 2);
            $table->json('snapshot');
            $table->timestamps();

            $table->unique(['tarifario_version_id', 'source_tarifario_id'], 'tarifario_version_item_source_unique');
            $table->index(['tarifario_version_id', 'servicio_id', 'peso_inicial', 'peso_final'], 'tarifario_version_item_match_idx');
        });

        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->foreignId('tarifario_version_id')->nullable()
                ->constrained('tarifario_versions')->restrictOnDelete();
            $table->json('tarifario_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('paquetes_ems', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tarifario_version_id');
            $table->dropColumn('tarifario_snapshot');
        });

        Schema::dropIfExists('tarifario_version_items');
        Schema::dropIfExists('tarifario_versions');
    }
};
