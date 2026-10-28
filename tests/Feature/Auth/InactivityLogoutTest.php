<?php

namespace Tests\Feature\Auth;

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class InactivityLogoutTest extends TestCase
{
    public function test_inactivity_invalidates_internal_session_and_redirects_to_login(): void
    {
        $this->assertInactivityLogout(false);
    }

    public function test_inactivity_logs_out_cliente_guard_and_redirects_to_client_login(): void
    {
        $this->assertInactivityLogout(true);
    }

    private function assertInactivityLogout(bool $cliente): void
    {
        $web = Mockery::mock();
        $web->shouldReceive('check')->andReturn(! $cliente);
        $web->shouldReceive('logout')->times($cliente ? 0 : 1);
        $client = Mockery::mock();
        $client->shouldReceive('check')->andReturn($cliente);
        $client->shouldReceive('logout')->times($cliente ? 1 : 0);
        Auth::shouldReceive('guard')->with('web')->andReturn($web);
        Auth::shouldReceive('guard')->with('cliente')->andReturn($client);

        $session = app('session.store');
        $session->start();
        $session->put('private-data', 'must disappear');
        $session->put('url.intended', '/dashboard');
        $session->regenerateToken();
        $oldId = $session->getId();
        $oldToken = $session->token();
        $request = Request::create('/logout?motivo=inactividad');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => null);

        $response = app(AuthenticatedSessionController::class)->destroyViaGet($request);

        $this->assertSame(route($cliente ? 'clientes.login' : 'login'), $response->getTargetUrl());
        $this->assertFalse($session->has('private-data'));
        $this->assertFalse($session->has('url.intended'));
        $this->assertNotSame($oldId, $session->getId());
        $this->assertNotSame($oldToken, $session->token());
        $this->assertStringContainsString('2 horas de inactividad', $session->get('status'));
    }
}
