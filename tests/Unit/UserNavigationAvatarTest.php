<?php

namespace Tests\Unit;

use App\Models\Cliente;
use App\Models\User;
use Tests\TestCase;

class UserNavigationAvatarTest extends TestCase
{
    public function test_navigation_avatar_is_a_small_local_asset(): void
    {
        $url = (new User)->adminlte_image();

        $this->assertSame(asset('images/AGBClogo1.png'), $url);
        $this->assertFileExists(public_path('images/AGBClogo1.png'));
        $this->assertLessThan(100 * 1024, filesize(public_path('images/AGBClogo1.png')));
    }

    public function test_client_without_photo_also_uses_the_local_avatar(): void
    {
        $cliente = new Cliente;

        $this->assertSame(asset('images/user-avatar.svg'), $cliente->adminlte_image());
        $cliente->avatar = 'https://example.test/client-photo.jpg';
        $this->assertSame($cliente->avatar, $cliente->adminlte_image());
    }

    public function test_early_menu_initializer_is_included_in_the_active_adminlte_plugins(): void
    {
        $html = view('adminlte::plugins', ['type' => 'js'])->render();

        $this->assertStringContainsString(asset('js/adminlte-navigation.js'), $html);
    }
}
