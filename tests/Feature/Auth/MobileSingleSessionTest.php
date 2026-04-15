<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MobileSingleSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_mobile_login_invalidates_the_previous_device_session(): void
    {
        $user = User::factory()->create([
            'alias' => 'driver001',
            'password' => 'password',
        ]);

        $firstLogin = $this->postJson('/api/mobile/login', [
            'login' => 'driver001',
            'password' => 'password',
            'device_name' => 'android-app-a',
        ]);

        $firstLogin->assertOk();
        $firstSessionCookie = $firstLogin->getCookie(config('session.cookie'));
        $this->assertNotNull($firstSessionCookie);

        $this->assertDatabaseCount('sessions', 1);

        $secondLogin = $this->postJson('/api/mobile/login', [
            'login' => 'driver001',
            'password' => 'password',
            'device_name' => 'android-app-b',
        ]);

        $secondLogin->assertOk();
        $secondSessionCookie = $secondLogin->getCookie(config('session.cookie'));
        $this->assertNotNull($secondSessionCookie);

        $this->assertDatabaseCount('sessions', 1);
        $remainingSessionId = DB::table('sessions')->value('id');
        $this->assertSame($secondSessionCookie->getValue(), $remainingSessionId);
        $this->assertNotSame($firstSessionCookie->getValue(), $secondSessionCookie->getValue());

        $this->withCookie(config('session.cookie'), $firstSessionCookie->getValue())
            ->getJson('/api/mobile/me')
            ->assertStatus(401)
            ->assertJson([
                'session_conflict' => true,
            ]);

        $this->withCookie(config('session.cookie'), $secondSessionCookie->getValue())
            ->getJson('/api/mobile/me')
            ->assertOk()
            ->assertJsonPath('id', $user->id);
    }
}
