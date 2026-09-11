<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ForbiddenRedirectTest extends TestCase
{
    public function test_route_permission_screen_redirects_to_public_tracking(): void
    {
        Route::middleware('web')->get('/testing/forbidden', function () {
            abort(403, 'No tienes permiso para acceder a esta ventana o accion.');
        });

        $this->get('/testing/forbidden')
            ->assertRedirect('https://trackingbo.correos.gob.bo:8100/inicio');
    }

    public function test_json_forbidden_response_keeps_its_403_status(): void
    {
        Route::get('/testing/forbidden-json', function () {
            abort(403, 'No autorizado.');
        });

        $this->getJson('/testing/forbidden-json')
            ->assertForbidden();
    }

    public function test_forbidden_form_action_keeps_its_403_status(): void
    {
        Route::post('/testing/forbidden-action', function () {
            abort(403, 'No autorizado.');
        });

        $this->post('/testing/forbidden-action')
            ->assertForbidden();
    }

    public function test_unrelated_browser_forbidden_response_keeps_its_403_status(): void
    {
        Route::get('/testing/other-forbidden', function () {
            abort(403, 'No autorizado.');
        });

        $this->get('/testing/other-forbidden')
            ->assertForbidden();
    }
}
