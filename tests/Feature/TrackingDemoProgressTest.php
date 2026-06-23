<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
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
                        'office' => 'PaÃ­s Origen: United States of America (the)',
                    ],
                    [
                        'created_at' => '2026-02-20 13:48:15',
                        'nombre_evento' => 'Paquete recibido en oficina de trÃ¡nsito.',
                        'office' => 'PaÃ­s Origen: United States of America (the)',
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
                        'office' => 'PaÃ­s Origen: Spain',
                    ],
                    [
                        'created_at' => '2026-02-13 05:57:00',
                        'nombre_evento' => 'Paquete enviado al extranjero.',
                        'office' => 'PaÃ­s Origen: Spain',
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
        $response->assertSee('data-country-iso="es"', false);
        $response->assertDontSee('>Bolivia<', false);
        $response->assertSee('La Paz', false);
    }

    public function test_inbound_international_tracking_without_destination_country_uses_latest_bolivian_office_as_destination(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'UR515134055CA',
                'servicio' => 'ORDINARIAS',
                'origen' => 'Canada',
                'destino' => null,
                'pais_destino' => null,
                'country_destino' => null,
                'meta' => [
                    'origin_country_code' => 'CA',
                    'origin_country_name' => 'Canada',
                    'destination_country_code' => null,
                    'destination_country_name' => null,
                ],
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'created_at' => '2026-06-02 19:43:56',
                        'nombre_evento' => 'Paquete recibido en oficina de entrega(Listo para entregar).',
                        'office' => 'BOLPBA - LA PAZ LC/AO',
                    ],
                    [
                        'created_at' => '2026-05-20 13:09:18',
                        'nombre_evento' => 'Recibir envase desde el extranjero (entrada) [Indirecto: receptaculo]',
                        'office' => 'BOSRZA - SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                        'nextOffice' => 'BOLPBA',
                        'detail' => 'Receptaculo: CAYTOABOLPBAAUN60084001100017',
                    ],
                    [
                        'created_at' => '2026-05-04 18:33:52',
                        'nombre_evento' => 'Paquete enviado al extranjero.',
                        'office' => 'PaÃ­s Origen: Canada',
                        'detail' => 'PaÃ­s Origen: Canada',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'tracking_captcha_verified_until' => now()->addMinutes(5)->timestamp,
            ])
            ->get('/trackingbo?codigo=UR515134055CA');

        $response->assertOk();
        $response->assertSee('data-country-iso="ca"', false);
        $response->assertSee('src="https://flagcdn.com/16x12/bo.png" alt="Bandera destino"', false);
        $response->assertSee('>La Paz<', false);
        $response->assertDontSee('>Nacional<', false);
        $response->assertDontSee('>Internacional<', false);
    }
}
