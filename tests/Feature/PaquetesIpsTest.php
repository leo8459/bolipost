<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaquetesIpsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_lists_paginated_ips_packages_using_the_server_side_token(): void
    {
        config()->set('services.tracking_sqlserver.paquetes_url', 'https://ips.test/api/tracking/paquetes');
        config()->set('services.tracking_sqlserver.token', '2|secret-token');

        Http::fake([
            'https://ips.test/*' => Http::response([
                'count' => 1,
                'data' => [[
                    'mailitm_pid' => 12,
                    'codigo' => 'CP556326695CN',
                    'codigo_s10' => 'CP556326695CN',
                    'fecha_registro' => '2026-08-12 14:17:00',
                    'tipo_servicio' => 'encomiendas',
                    'peso' => 4.8,
                    'clase_correo' => 'PARCELS (CP)',
                    'contenido' => null,
                    'estado_postal' => 'Normal',
                    'origen' => ['codigo' => 'CN', 'nombre' => 'China'],
                    'destino' => ['codigo' => 'BO', 'nombre' => 'Bolivia'],
                ]],
                'meta' => ['page' => 2, 'per_page' => 50, 'count' => 1, 'total' => 148],
            ]),
        ]);

        $response = $this
            ->withoutMiddleware()
            ->get(route('paquetes-ips.index', [
                'page' => 2,
                'per_page' => 50,
                'q' => 'CP556326695CN',
                'fecha_desde' => '2026-08-01',
                'fecha_hasta' => '2026-08-15',
            ]));

        $response
            ->assertOk()
            ->assertSee('Paquetes IPS')
            ->assertSee('Total filtrado:')
            ->assertSee('148')
            ->assertSee('CP556326695CN')
            ->assertSee('12/08/2026 14:17')
            ->assertSee('PARCELS (CP)')
            ->assertSee('China')
            ->assertSee('Bolivia');

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://ips.test/api/tracking/paquetes?page=2&per_page=50&q=CP556326695CN&fecha_desde=2026-08-01&fecha_hasta=2026-08-15'
                && $request->hasHeader('Authorization', 'Bearer 2|secret-token');
        });
    }

    public function test_it_rejects_an_inverted_date_range_without_calling_ips(): void
    {
        Http::fake();

        $response = $this
            ->withoutMiddleware()
            ->get(route('paquetes-ips.index', [
                'fecha_desde' => '2026-08-15',
                'fecha_hasta' => '2026-08-01',
            ]));

        $response
            ->assertSessionHasErrors('fecha_hasta')
            ->assertRedirect();

        Http::assertNothingSent();
    }
}
