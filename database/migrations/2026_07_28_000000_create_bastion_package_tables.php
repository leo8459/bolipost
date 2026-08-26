<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bastion_ems')) {
            Schema::create('bastion_ems', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_origen')->nullable()->index();
                $table->string('origen')->nullable();
                $table->string('tipo_correspondencia')->nullable();
                $table->string('servicio_especial')->nullable();
                $table->text('contenido')->nullable();
                $table->unsignedInteger('cantidad')->nullable();
                $table->decimal('peso', 10, 3)->nullable();
                $table->string('codigo')->nullable();
                $table->string('cod_especial', 20)->nullable();
                $table->decimal('precio', 10, 2)->nullable();
                $table->string('nombre_remitente')->nullable();
                $table->string('nombre_envia')->nullable();
                $table->string('carnet')->nullable();
                $table->string('telefono_remitente')->nullable();
                $table->string('nombre_destinatario')->nullable();
                $table->string('telefono_destinatario')->nullable();
                $table->string('direccion')->nullable();
                $table->string('referencia')->nullable();
                $table->string('imagen')->nullable();
                $table->text('observacion')->nullable();
                $table->string('ciudad')->nullable();
                $table->unsignedBigInteger('tarifario_id')->nullable();
                $table->unsignedBigInteger('estado_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('justificacion')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bastion_contratos')) {
            Schema::create('bastion_contratos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_origen')->nullable()->index();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->unsignedBigInteger('empresa_id')->nullable();
                $table->string('codigo')->nullable();
                $table->string('codigo_madre')->nullable();
                $table->string('cod_especial', 50)->nullable();
                $table->unsignedBigInteger('estados_id')->nullable();
                $table->string('origen')->nullable();
                $table->string('provincia_origen')->nullable();
                $table->string('destino')->nullable();
                $table->string('nombre_r')->nullable();
                $table->string('telefono_r')->nullable();
                $table->text('contenido')->nullable();
                $table->string('cantidad')->nullable();
                $table->string('direccion_r')->nullable();
                $table->string('nombre_d')->nullable();
                $table->string('telefono_d')->nullable();
                $table->string('direccion_d')->nullable();
                $table->string('mapa')->nullable();
                $table->string('provincia')->nullable();
                $table->decimal('peso', 10, 3)->nullable();
                $table->decimal('precio', 10, 2)->nullable();
                $table->unsignedBigInteger('tarifa_contrato_id')->nullable();
                $table->dateTime('fecha_recojo')->nullable();
                $table->text('observacion')->nullable();
                $table->text('justificacion')->nullable();
                $table->string('imagen')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bastion_certi')) {
            Schema::create('bastion_certi', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_origen')->nullable()->index();
                $table->string('codigo')->nullable();
                $table->string('cod_especial')->nullable();
                $table->unsignedBigInteger('servicio_id')->nullable();
                $table->string('destinatario')->nullable();
                $table->integer('telefono')->nullable();
                $table->string('cuidad')->nullable();
                $table->string('zona')->nullable();
                $table->string('ventanilla')->nullable();
                $table->decimal('peso', 10, 3)->nullable();
                $table->decimal('precio', 10, 2)->nullable();
                $table->string('tipo')->nullable();
                $table->string('aduana')->nullable();
                $table->text('observaciones')->nullable();
                $table->unsignedBigInteger('fk_estado')->nullable();
                $table->unsignedBigInteger('fk_ventanilla')->nullable();
                $table->string('imagen')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('bastion_ordi')) {
            Schema::create('bastion_ordi', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('id_origen')->nullable()->index();
                $table->string('codigo')->nullable();
                $table->unsignedBigInteger('servicio_id')->nullable();
                $table->string('destinatario')->nullable();
                $table->string('telefono', 30)->nullable();
                $table->string('ciudad')->nullable();
                $table->string('zona')->nullable();
                $table->decimal('peso', 10, 3)->nullable();
                $table->decimal('precio', 10, 2)->nullable();
                $table->string('aduana', 50)->nullable();
                $table->text('observaciones')->nullable();
                $table->string('cod_especial')->nullable();
                $table->unsignedBigInteger('fk_ventanilla')->nullable();
                $table->unsignedBigInteger('fk_estado')->nullable();
                $table->string('imagen')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('bastion_ordi');
        Schema::dropIfExists('bastion_certi');
        Schema::dropIfExists('bastion_contratos');
        Schema::dropIfExists('bastion_ems');
    }
};
