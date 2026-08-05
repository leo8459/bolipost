<?php

namespace Tests\Feature;

use App\Support\BitacoraCn33Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class BitacoraEnvioCn33Test extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('paquetes_ems', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('codigo')->nullable();
            $table->string('origen')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('nombre_destinatario')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamp('envio_cn33')->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('codigo')->nullable();
            $table->string('origen')->nullable();
            $table->string('destino')->nullable();
            $table->string('nombre_d')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamp('envio_cn33')->nullable();
            $table->timestamps();
        });

        Schema::create('solicitud_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('codigo_solicitud')->nullable();
            $table->string('barcode')->nullable();
            $table->string('origen')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('nombre_destinatario')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamp('envio_cn33')->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_int', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('codigo')->nullable();
            $table->string('origen')->nullable();
            $table->string('destino')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamp('envio_cn33')->nullable();
            $table->timestamps();
        });

        foreach (['eventos_ems', 'eventos_contrato'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('codigo');
                $table->unsignedBigInteger('evento_id');
                $table->timestamps();
            });
        }

        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        foreach (['bitacoras', 'eventos_contrato', 'eventos_ems', 'paquetes_int', 'solicitud_clientes', 'paquetes_contrato', 'paquetes_ems'] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        parent::tearDown();
    }

    public function test_bitacora_usa_envio_cn33_como_fecha_de_despacho(): void
    {
        DB::table('paquetes_ems')->insert([
            'cod_especial' => 'LPZ00001',
            'codigo' => 'EMS-001',
            'origen' => 'LA PAZ',
            'ciudad' => 'COCHABAMBA',
            'peso' => 2.5,
            'precio' => 10,
            'envio_cn33' => '2026-08-05 10:15:30',
            'created_at' => '2026-07-01 08:00:00',
            'updated_at' => '2026-08-05 10:15:30',
        ]);

        DB::table('eventos_ems')->insert([
            'codigo' => 'EMS-001',
            'evento_id' => 240,
            'created_at' => '2026-07-02 09:00:00',
            'updated_at' => '2026-07-02 09:00:00',
        ]);

        $summary = app(BitacoraCn33Service::class)->getDispatchSummary('LPZ00001');

        $this->assertTrue($summary['exists']);
        $this->assertSame('2026-08-05 10:15:30', $summary['dispatch_created_at']);
        $this->assertSame('2026-07-01 08:00:00', $summary['first_created_at']);
    }
}
