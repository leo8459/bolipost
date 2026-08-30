<?php

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\EnsureEmpresaContractUsersActive;
use App\Models\Cliente;
use App\Services\AlertaEmpresaService;
use App\Services\EmpresaContractUserSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class EnsureEmpresaContractUsersActiveTest extends TestCase
{
    public function test_ignores_authenticated_client_when_the_default_guard_is_cliente(): void
    {
        $cliente = new Cliente;
        $cliente->id = 47;

        Auth::guard('cliente')->setUser($cliente);
        Auth::shouldUse('cliente');

        $syncService = Mockery::mock(EmpresaContractUserSyncService::class);
        $syncService->shouldNotReceive('ensureAuthenticatedUserIsActive');
        $syncService->shouldReceive('buildExpirationAlertsForUser')
            ->once()
            ->with(null)
            ->andReturn(collect());

        $alertaService = Mockery::mock(AlertaEmpresaService::class);
        $alertaService->shouldReceive('siguienteNoLeida')
            ->once()
            ->with(null)
            ->andReturnNull();

        $middleware = new EnsureEmpresaContractUsersActive($syncService, $alertaService);
        $response = $middleware->handle(
            Request::create('/clientes/dashboard'),
            fn (): Response => response('dashboard cliente')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('dashboard cliente', $response->getContent());
    }
}
