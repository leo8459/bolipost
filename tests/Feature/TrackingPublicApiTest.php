<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrackingPublicApiTest extends TestCase
{
    public function test_public_tracking_api_does_not_require_captcha(): void
    {
        $response = $this->getJson('/api/public/tracking/eventos?codigo=AB123');

        $response->assertStatus(404);
        $this->assertNotEquals(422, $response->getStatusCode());
    }
}
