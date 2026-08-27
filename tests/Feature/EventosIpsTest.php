<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventosIpsTest extends TestCase
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

    public function test_it_searches_ips_package_data_and_events_using_the_server_side_token(): void
    {
        config()->set('services.tracking_sqlserver.eventos_todos_url', 'https://ips.test/api/tracking/eventos-todos');
        config()->set('services.tracking_sqlserver.paquetes_url', 'https://ips.test/api/tracking/paquetes');
        config()->set('services.tracking_sqlserver.token', '2|secret-token');

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/eventos-todos')) {
                return Http::response([
                    'codigo' => 'LX050606084NL',
                    'tipo_servicio' => 'ordinarias',
                    'origen' => 'Netherlands (the)',
                    'destino' => 'Bolivia (Plurinational State of)',
                    'meta' => [
                        'origin_country_code' => 'NL',
                        'destination_country_code' => 'BO',
                    ],
                    'eventos_externos' => [[
                        'mailitM_PID' => 'ips-event-id',
                        'mailitM_FID' => 'LX050606084NL',
                        'eventType' => 'Paquete recibido en oficina de tránsito.',
                        'eventDate' => '2026-08-18 19:50:39',
                        'office' => 'BOSRZA - SANTA CRUZ',
                        'scanned' => 'AGBC -operador',
                        'workstation' => 'DESKTOP-IPS',
                        'condition' => 'Envío recibido en buen estado',
                        'nextOffice' => 'BOCBBA - COCHABAMBA',
                        'detail' => 'Receptáculo de prueba',
                    ]],
                ]);
            }

            return Http::response([
                'data' => [[
                    'mailitm_pid' => 'ips-package-id',
                    'codigo' => 'LX050606084NL',
                    'codigo_s10' => 'LX050606084NL',
                    'fecha_registro' => '2026-08-18 19:50:39',
                    'numero_despacho' => 'NL123',
                    'tipo_servicio' => 'ordinarias',
                    'peso' => 0.117,
                    'clase_correo' => 'LETTERS (LC/AO)',
                    'contenido' => 'Documentos',
                    'estado_postal' => 'Normal',
                    'telefono' => '70000000',
                    'telefonos' => ['remitente' => '71111111', 'destinatario' => '72222222'],
                    'origen' => ['codigo' => 'NL', 'nombre' => 'Netherlands (the)'],
                    'destino' => ['codigo' => 'BO', 'nombre' => 'Bolivia (Plurinational State of)'],
                ]],
            ]);
        });

        $response = $this
            ->withoutMiddleware()
            ->get(route('eventos-ips.index', ['codigo' => 'lx050606084nl']));

        $response
            ->assertOk()
            ->assertSee('EVENTOS IPS')
            ->assertSee('ÚLTIMO MOVIMIENTO')
            ->assertSee('ips-package-id')
            ->assertSee('0,117 kg')
            ->assertSee('LETTERS (LC/AO)')
            ->assertSee('🇳🇱')
            ->assertSee('🇧🇴')
            ->assertSee('Recorrido del envío')
            ->assertSee('Paquete recibido en oficina de tránsito.')
            ->assertSee('BOSRZA - SANTA CRUZ')
            ->assertSee('Receptáculo de prueba');

        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer 2|secret-token'));
    }

    public function test_it_does_not_call_ips_until_a_tracking_code_is_entered(): void
    {
        Http::fake();

        $this->withoutMiddleware()
            ->get(route('eventos-ips.index'))
            ->assertOk()
            ->assertSee('Sigue tu envío de forma sencilla');

        Http::assertNothingSent();
    }
}
