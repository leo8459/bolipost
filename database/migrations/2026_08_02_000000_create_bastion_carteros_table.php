<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'id',
        'id_paquetes_ems',
        'id_paquetes_certi',
        'id_estados',
        'id_user',
        'created_at',
        'updated_at',
        'intento',
        'recibido_por',
        'descripcion',
        'id_paquetes_contrato',
        'foto',
        'imagen',
        'id_paquetes_ordi',
        'imagen_devolucion',
        'id_solicitud_cliente',
        'id_estado_anterior',
    ];

    public function up(): void
    {
        if (Schema::hasTable('bastion_carteros')) {
            return;
        }

        Schema::create('bastion_carteros', function (Blueprint $table) {
            // Se conservan los IDs originales, pero no las claves foraneas, para
            // que el bastion permanezca independiente de las tablas operativas.
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('id_paquetes_ems')->nullable();
            $table->unsignedBigInteger('id_paquetes_certi')->nullable();
            $table->unsignedBigInteger('id_estados');
            $table->unsignedBigInteger('id_user');
            $table->timestamps();
            $table->unsignedInteger('intento')->default(0);
            $table->string('recibido_por')->nullable();
            $table->text('descripcion')->nullable();
            $table->unsignedBigInteger('id_paquetes_contrato')->nullable();
            $table->string('foto')->nullable();
            $table->text('imagen')->nullable();
            $table->unsignedBigInteger('id_paquetes_ordi')->nullable();
            $table->text('imagen_devolucion')->nullable();
            $table->unsignedBigInteger('id_solicitud_cliente')->nullable();
            $table->unsignedBigInteger('id_estado_anterior')->nullable();
        });

        if (Schema::hasTable('cartero')) {
            DB::table('bastion_carteros')->insertUsing(
                self::COLUMNS,
                DB::table('cartero')->select(self::COLUMNS)
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bastion_carteros');
    }
};
