<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class TrackingDemoProgressTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('servicio')) {
            Schema::create('servicio', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_servicio');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('destino')) {
            Schema::create('destino', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_destino');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('ciudad')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('eventos')) {
            Schema::create('eventos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre_evento');
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('eventos_certi')) {
            Schema::create('eventos_certi', function (Blueprint $table) {
                $table->id();
                $table->string('codigo');
                $table->unsignedBigInteger('evento_id')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('paquetes_certi')) {
            Schema::create('paquetes_certi', function (Blueprint $table) {
                $table->id();
                $table->string('codigo')->nullable();
                $table->string('cuidad')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_international_transit_event_maps_to_expedicion_not_ventanilla(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'LH266067312US',
                'servicio' => 'ORDINARIAS',
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'created_at' => '2026-02-21 05:57:15',
                        'nombre_evento' => 'Paquete enviado al extranjero.',
                        'office' => 'País Origen: United States of America (the)',
                    ],
                    [
                        'created_at' => '2026-02-20 13:48:15',
                        'nombre_evento' => 'Paquete recibido en oficina de tránsito.',
                        'office' => 'País Origen: United States of America (the)',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'tracking_captcha_verified_until' => now()->addMinutes(5)->timestamp,
            ])
            ->get('/trackingbo?codigo=LH266067312US');

        $response->assertOk();
        $response->assertSee('Paso actual: <strong>Expedicion</strong>', false);
        $response->assertDontSee('Paso actual: <strong>Admision</strong>', false);
        $response->assertDontSee('Paso actual: <strong>Ventanilla</strong>', false);
    }

    public function test_mixed_international_tracking_keeps_external_origin_country_in_header(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        DB::table('eventos')->insert([
            'id' => 700,
            'nombre_evento' => 'Paquete entregado exitosamente.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('paquetes_certi')->insert([
            'codigo' => 'RE872013283ES',
            'cuidad' => 'LA PAZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('eventos_certi')->insert([
            'id' => 7100,
            'codigo' => 'RE872013283ES',
            'evento_id' => 700,
            'user_id' => 1,
            'created_at' => '2026-03-12 14:35:00',
            'updated_at' => '2026-03-12 14:35:00',
        ]);

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'RE872013283ES',
                'servicio' => 'CERTI',
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'created_at' => '2026-02-17 04:15:00',
                        'nombre_evento' => 'Paquete incluido en la saca de envio.',
                        'office' => 'País Origen: Spain',
                    ],
                    [
                        'created_at' => '2026-02-13 05:57:00',
                        'nombre_evento' => 'Paquete enviado al extranjero.',
                        'office' => 'País Origen: Spain',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'tracking_captcha_verified_until' => now()->addMinutes(5)->timestamp,
            ])
            ->get('/trackingbo?codigo=RE872013283ES');

        $response->assertOk();
        $response->assertSee('>España<', false);
        $response->assertDontSee('>Bolivia<', false);
        $response->assertSee('La Paz', false);
    }
}
