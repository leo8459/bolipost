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

    public function test_customs_route_shows_both_substeps_and_guidance_while_event_31_is_current(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'LP666440208MY',
                'servicio' => 'ORDINARIAS',
                'origen' => 'Malaysia',
                'destino' => 'Bolivia',
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'codigo_evento' => 31,
                        'created_at' => '2026-09-14 21:06:00',
                        'nombre_evento' => 'Send item to customs (Inb)',
                        'office' => 'SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                    ],
                    [
                        'codigo_evento' => 32,
                        'created_at' => '2026-09-14 17:40:00',
                        'nombre_evento' => 'Receive item at delivery office (Inb)',
                        'office' => 'SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'tracking_captcha_verified_until' => now()->addMinutes(5)->timestamp,
            ])
            ->get('/trackingbo?codigo=LP666440208MY');

        $response->assertOk();
        $response->assertSee('no figura como listo para recoger');
        $response->assertSee('<h1>En Aduana</h1>', false);
        $response->assertDontSee('pago indicados por Aduana', false);
        $response->assertSee('Ventanilla igual a Aduana, una sola etapa', false);
        $response->assertSee('split-step-art', false);
        $response->assertDontSee('class="step-state">En Aduana</span>', false);
        $response->assertDontSee('Tu paquete esta listo para entregar. Debes pasar a recoger', false);
    }

    public function test_counter_stage_restores_the_pickup_message_for_a_customs_package(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'LP666440208MY',
                'servicio' => 'ORDINARIAS',
                'origen' => 'Malaysia',
                'destino' => 'Bolivia',
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'codigo_evento' => 32,
                        'created_at' => '2026-09-15 09:10:00',
                        'nombre_evento' => 'Receive item at delivery office (Inb)',
                    ],
                    [
                        'codigo_evento' => 31,
                        'created_at' => '2026-09-14 21:06:00',
                        'nombre_evento' => 'Send item to customs (Inb)',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'tracking_captcha_verified_until' => now()->addMinutes(5)->timestamp,
            ])
            ->get('/trackingbo?codigo=LP666440208MY');

        $response->assertOk();
        $response->assertSee('Tu paquete está listo para entregar. Puedes pasar a recogerlo en el punto de Ventanilla indicado en el seguimiento.');
        $response->assertDontSee('class="step-state">Puede pasar a recoger</span>', false);
        $response->assertSee('Ventanilla igual a Aduana, una sola etapa', false);
    }

    public function test_customs_information_at_destination_marks_combined_step_ready_for_pickup(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'LX093096600NL',
                'servicio' => 'ORDINARIAS',
                'origen' => 'Netherlands',
                'destino' => 'Bolivia',
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'codigo_evento' => 34,
                        'created_at' => '2026-09-18 18:00:42',
                        'nombre_evento' => 'Registrar información de aduanas sobre el envío (entrada)',
                        'office' => 'BOORUB - ORURO',
                        'ciudad_destino' => 'Oruro',
                    ],
                    [
                        'codigo_evento' => 31,
                        'created_at' => '2026-09-14 20:28:28',
                        'nombre_evento' => 'Send item to customs (Inb)',
                        'office' => 'BOSRZA - SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                    ],
                    [
                        'codigo_evento' => 30,
                        'created_at' => '2026-09-11 15:14:32',
                        'nombre_evento' => 'Recibir envío en oficina de cambio (entrada)',
                        'office' => 'BOSRZA - SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'tracking_captcha_verified_until' => now()->addMinutes(5)->timestamp,
            ])
            ->get('/trackingbo?codigo=LX093096600NL');

        $response->assertOk();
        $response->assertSee('<h1>Listo para recoger</h1>', false);
        $response->assertDontSee('pago indicados por Aduana', false);
        $response->assertSee('Calle Presidente Montes Esquina Junin', false);
        $response->assertSee('Ventanilla igual a Aduana, una sola etapa', false);
    }

    public function test_customs_event_in_santa_cruz_is_not_pickup_ready_for_la_paz_destination(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'EC256579158BE',
                'servicio' => 'CERTIFICADAS',
                'origen' => 'Belgium',
                'destino' => 'Bolivia',
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'codigo_evento' => 31,
                        'created_at' => '2026-09-01 20:44:57',
                        'nombre_evento' => 'Send item to customs (Inb)',
                        'office' => 'BOSRZA - SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                        'ciudad_destino' => 'La Paz',
                    ],
                    [
                        'codigo_evento' => 30,
                        'created_at' => '2026-08-28 18:22:53',
                        'nombre_evento' => 'Receive item at inward office of exchange',
                        'office' => 'BOSRZA - SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'tracking_captcha_verified_until' => now()->addMinutes(5)->timestamp,
            ])
            ->get('/trackingbo?codigo=EC256579158BE');

        $response->assertOk();
        $response->assertSee('<h1>En Aduana</h1>', false);
        $response->assertDontSee('Tu paquete esta listo para entregar', false);
        $response->assertDontSee('Avenida Mariscal Santa Cruz', false);
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

    public function test_result_page_allows_searching_another_code_without_returning_to_home(): void
    {
        config()->set('services.tracking_sqlserver.base_url', 'https://tracking.test/api/public/tracking/eventos');
        config()->set('services.tracking_sqlserver.token', 'test-token');

        Http::fake([
            'https://tracking.test/*' => Http::response([
                'codigo' => 'LX309020748ES',
                'servicio' => 'CERTI',
                'eventos_locales' => [],
                'eventos_externos' => [
                    [
                        'created_at' => '2026-08-11 12:20:00',
                        'nombre_evento' => 'Paquete entregado exitosamente. - LA PAZ',
                        'office' => 'País Origen: Spain',
                    ],
                ],
            ], 200),
        ]);

        $response = $this
            ->withSession([
                'tracking_captcha_verified_until' => now()->addMinutes(5)->timestamp,
            ])
            ->get('/trackingbo?codigo=LX309020748ES');

        $response->assertOk();
        $response->assertSee('Buscar otro codigo', false);
        $response->assertSee('name="codigo"', false);
        $response->assertSee('value="LX309020748ES"', false);
        $response->assertSee('type="submit">Buscar ahora</button>', false);
        $response->assertDontSee('href="' . url('/#inicio') . '"', false);
    }
}
