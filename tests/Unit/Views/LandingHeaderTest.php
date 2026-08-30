<?php

namespace Tests\Unit\Views;

use Tests\TestCase;

class LandingHeaderTest extends TestCase
{
    public function test_client_login_links_stay_on_the_current_environment(): void
    {
        $html = view('partials.landing-header', [
            'landingNewsTicker' => [],
        ])->render();

        $this->assertSame(2, substr_count($html, 'href="/clientes/login"'));
        $this->assertStringNotContainsString('trackingbo.correos.gob.bo:8100/clientes/login', $html);
    }
}
