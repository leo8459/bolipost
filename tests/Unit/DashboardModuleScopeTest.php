<?php

namespace Tests\Unit;

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class DashboardModuleScopeTest extends TestCase
{
    public function test_dashboard_uses_only_ems_and_contracts_even_with_old_module_filters(): void
    {
        $controller = app(DashboardController::class);
        $resolver = new ReflectionMethod($controller, 'resolveDashboardModulosSeleccionados');

        $this->assertSame(
            ['ems', 'contrato'],
            $resolver->invoke($controller, Request::create('/dashboard'))
        );
        $this->assertSame(
            ['ems'],
            $resolver->invoke($controller, Request::create('/dashboard', 'GET', [
                'modules' => ['ems', 'certi', 'ordi'],
            ]))
        );
        $this->assertSame(
            ['ems', 'contrato'],
            $resolver->invoke($controller, Request::create('/dashboard', 'GET', [
                'modules' => ['certi', 'ordi'],
            ]))
        );
    }
}
