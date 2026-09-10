<?php

namespace Tests\Feature;

use App\Http\Controllers\FacturacionQrMonitorController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Mockery;
use Tests\TestCase;

class FacturacionQrMonitorSignatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_monitor_display_accepts_relative_signed_url_from_any_host(): void
    {
        $relativeUrl = URL::temporarySignedRoute(
            'facturacion.monitor.display',
            now()->addMinute(),
            ['monitor' => 'user-123'],
            false
        );

        $response = $this
            ->withServerVariables(['HTTP_HOST' => 'otra-pc.local'])
            ->get($relativeUrl);

        $response->assertOk();
        $response->assertSee('Monitor QR');
    }

    public function test_signed_url_endpoint_returns_relative_monitor_url(): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 77;
        $user->shouldReceive('can')
            ->with('feature.dashboard.facturacion')
            ->andReturn(true);
        $user->shouldReceive('getAuthIdentifier')
            ->andReturn(77);

        $request = Request::create('/facturacion/monitor/signed-url', 'GET');
        $request->setUserResolver(fn () => $user);

        $response = app(FacturacionQrMonitorController::class)->signedUrl($request);
        $payload = $response->getData(true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(true, $payload['ok'] ?? null);
        $this->assertSame('user-77', $payload['monitor_key'] ?? null);

        $url = (string) ($payload['url'] ?? '');
        $this->assertStringStartsWith('/facturacion/monitor/display/user-77?', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringNotContainsString('://', $url);
    }
}
