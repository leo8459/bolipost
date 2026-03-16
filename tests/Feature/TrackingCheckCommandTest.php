<?php

namespace Tests\Feature;

use App\Models\TrackingSubscription;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TrackingCheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('tracking_subscriptions');
        Schema::create('tracking_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 13);
            $table->text('fcm_token');
            $table->string('package_name', 120)->nullable();
            $table->text('last_sig')->nullable();
            $table->timestamps();
        });
    }

    public function test_tracking_check_uses_public_tracking_payload_format(): void
    {
        config()->set('app.env', 'testing');

        TrackingSubscription::create([
            'codigo' => 'AB123456789CD',
            'fcm_token' => '',
            'package_name' => 'Mi paquete',
            'last_sig' => null,
        ]);

        Http::fake([
            'https://admin.correos.gob.bo:8101/api/public/tracking/eventos*' => Http::response([
                'tipo' => 'tracking_eventos',
                'filtro' => ['codigo' => 'AB123456789CD'],
                'existe_paquete' => true,
                'fuente' => 'api',
                'total_registros' => 1,
                'resultado' => [
                    [
                        'codigo' => 'AB123456789CD',
                        'total_eventos' => 1,
                        'eventos' => [
                            [
                                'id' => 77,
                                'evento_id' => 9,
                                'created_at' => '2026-03-16 12:34:56',
                                'updated_at' => '2026-03-16 12:34:56',
                                'nombre_evento' => 'Entregado a distribucion',
                                'servicio' => 'TRACKING',
                                'tabla_origen' => 'api_sqlserver',
                                'office' => 'LPZ',
                                'condition' => 'EN CURSO',
                                'next_office' => 'SCZ',
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $this->artisan('tracking:check')->assertSuccessful();

        $sub = TrackingSubscription::firstOrFail();

        $this->assertSame(
            'tracking|2026-03-16 12:34:56|77|9|Entregado a distribucion|LPZ|EN CURSO|SCZ',
            $sub->last_sig
        );

        Http::assertSent(function ($request) {
            return $request->method() === 'GET'
                && str_contains($request->url(), '/api/public/tracking/eventos')
                && $request['codigo'] === 'AB123456789CD';
        });
    }
}
