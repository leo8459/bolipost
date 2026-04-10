<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrackingPublicApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('eventos')) {
            Schema::create('eventos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_evento');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('eventos_ems')) {
            Schema::create('eventos_ems', function (Blueprint $table) {
                $table->id();
                $table->string('codigo');
                $table->unsignedBigInteger('evento_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('paquetes_ems')) {
            Schema::create('paquetes_ems', function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->nullable();
                $table->string('origen')->nullable();
                $table->string('ciudad')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_public_tracking_api_does_not_require_captcha(): void
    {
        $response = $this->getJson('/api/public/tracking/eventos?codigo=AB123');

        $response->assertStatus(404);
        $this->assertNotEquals(422, $response->getStatusCode());
    }

    public function test_public_tracking_api_combines_external_and_local_events_for_same_package(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        DB::table('eventos')->insert([
            'id' => 501,
            'nombre_evento' => 'Recibido en ventanilla',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('paquetes_ems')->insert([
            'codigo' => 'AB123456789CD',
            'origen' => 'LA PAZ',
            'ciudad' => 'SANTA CRUZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('eventos_ems')->insert([
            'id' => 9001,
            'codigo' => 'AB123456789CD',
            'evento_id' => 501,
            'user_id' => 1,
            'created_at' => '2026-04-10 09:00:00',
            'updated_at' => '2026-04-10 09:00:00',
        ]);

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'AB123456789CD',
                'servicio' => 'EMS',
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'id' => 77,
                        'evento_id' => 9,
                        'created_at' => '2026-04-10 10:00:00',
                        'nombre_evento' => 'Llego a oficina de cambio',
                        'office' => 'LPZ',
                        'condition' => 'EN CURSO',
                        'nextOffice' => 'SCZ',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->getJson('/api/public/tracking/eventos?codigo=AB123456789CD');

        $response
            ->assertOk()
            ->assertJsonPath('existe_paquete', true)
            ->assertJsonPath('fuente', 'mixta')
            ->assertJsonPath('total_registros', 2)
            ->assertJsonCount(1, 'resultado')
            ->assertJsonPath('resultado.0.codigo', 'AB123456789CD')
            ->assertJsonPath('resultado.0.total_eventos', 2)
            ->assertJsonPath('resultado.0.eventos.0.nombre_evento', 'Llego a oficina de cambio')
            ->assertJsonPath('resultado.0.eventos.1.nombre_evento', 'Recibido en ventanilla');
    }
}
