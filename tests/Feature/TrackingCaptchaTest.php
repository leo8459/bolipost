<?php

namespace Tests\Feature;

use Tests\TestCase;

class TrackingCaptchaTest extends TestCase
{
    public function test_tracking_api_requires_captcha_answer(): void
    {
        $response = $this
            ->withSession([
                'tracking_captcha' => [
                    'question' => 'AB7K2',
                    'answer' => 'AB7K2',
                ],
            ])
            ->getJson('/api/busqueda/ems-eventos?codigo=AB123');

        $response
            ->assertStatus(422)
            ->assertJsonPath('message', 'Completa la verificacion de seguridad.');
    }

    public function test_tracking_api_accepts_valid_captcha_answer(): void
    {
        $response = $this
            ->withSession([
                'tracking_captcha' => [
                    'question' => 'AB7K2',
                    'answer' => 'AB7K2',
                ],
            ])
            ->getJson('/api/busqueda/ems-eventos?codigo=AB123&captcha_answer=ab7k2');

        $response->assertStatus(404);
        $this->assertNotEquals(422, $response->getStatusCode());
    }
}
