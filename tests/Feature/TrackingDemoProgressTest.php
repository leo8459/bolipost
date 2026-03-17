<?php

namespace Tests\Feature;

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
    }

    public function test_international_transit_event_does_not_map_to_ventanilla(): void
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
        $response->assertSee('Paso actual: <strong>Admision</strong>', false);
        $response->assertDontSee('Paso actual: <strong>Ventanilla</strong>', false);
    }
}
