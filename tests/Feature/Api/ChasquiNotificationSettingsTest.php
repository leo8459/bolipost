<?php

namespace Tests\Feature\Api;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ChasquiNotificationSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('app_settings');
        parent::tearDown();
    }

    public function test_administrador_configura_cada_cuantos_minutos_se_notifica(): void
    {
        $this->withViewErrors([])->get('/configuracion/aplicacion')
            ->assertOk()
            ->assertSee('Notificaciones de ChasquiApp')
            ->assertSee('Avisar cada cuantos minutos');

        $this->put('/configuracion/aplicacion', [
            'latestVersion' => '1.0.0',
            'minimumVersion' => '1.0.0',
            'forceUpdate' => '0',
            'carteroNotificationEnabled' => '1',
            'carteroNotificationIntervalMinutes' => '45',
            'carteroNotificationTitle' => 'ChasquiApp',
            'carteroNotificationMessage' => 'Tienes paquetes pendientes',
            'facturacionShowFacturaElectronica' => '1',
            'facturacionShowQrFactura' => '1',
            'facturacionShowQrSolo' => '1',
        ])->assertRedirect();

        $this->assertSame('45', DB::table('app_settings')
            ->where('key', 'chasqui.notifications.interval_minutes')
            ->value('value'));
        $this->assertSame('Tienes paquetes pendientes', DB::table('app_settings')
            ->where('key', 'chasqui.notifications.message')
            ->value('value'));
    }

    public function test_intervalo_menor_a_quince_minutos_es_rechazado(): void
    {
        $this->put('/configuracion/aplicacion', [
            'latestVersion' => '1.0.0',
            'minimumVersion' => '1.0.0',
            'carteroNotificationIntervalMinutes' => '5',
            'carteroNotificationTitle' => 'ChasquiApp',
            'carteroNotificationMessage' => 'Tienes paquetes pendientes',
        ])->assertSessionHasErrors('carteroNotificationIntervalMinutes');
    }
}
