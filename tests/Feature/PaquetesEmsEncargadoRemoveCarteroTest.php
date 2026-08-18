<?php

namespace Tests\Feature;

use Tests\TestCase;

class PaquetesEmsEncargadoRemoveCarteroTest extends TestCase
{
    public function test_encargado_view_always_shows_remove_cartero_and_disables_it_without_assignment(): void
    {
        $template = file_get_contents(resource_path('views/paquetes_ems/encargado.blade.php'));

        $this->assertStringContainsString("route('paquetes-ems.encargado.quitar-cartero')", $template);
        $this->assertStringContainsString("(int) (\$paquete->cartero_user_id ?? 0) > 0", $template);
        $this->assertStringContainsString('Quitar cartero', $template);
        $this->assertStringContainsString('Dejar sin asignacion', $template);
        $this->assertStringContainsString('Ya esta sin asignacion', $template);
        $this->assertStringContainsString('disabled title="Este envio ya esta sin cartero asignado"', $template);
    }

    public function test_remove_cartero_route_and_handler_keep_the_assignment_row(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PaquetesEmsController.php'));

        $this->assertStringContainsString("name('paquetes-ems.encargado.quitar-cartero')", $routes);
        $this->assertStringContainsString('public function quitarCarteroEncargado(Request $request)', $controller);
        $this->assertStringContainsString('$assignment->id_user = null;', $controller);
        $this->assertStringNotContainsString('$assignment->delete();', $controller);
        $this->assertStringContainsString('EncargadoEvent::CARTERO_QUITADO', $controller);
        $this->assertStringContainsString('$eventName', $controller);
    }

    public function test_cartero_user_column_is_made_nullable(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_12_120000_make_cartero_user_nullable.php'));

        $this->assertStringContainsString("unsignedBigInteger('id_user')->nullable()->change()", $migration);
    }

    public function test_remove_cartero_is_available_as_an_independent_role_permission(): void
    {
        $registry = file_get_contents(app_path('Support/AclPermissionRegistry.php'));
        $controller = file_get_contents(app_path('Http/Controllers/PaquetesEmsController.php'));
        $migration = file_get_contents(database_path('migrations/2026_08_12_130000_add_encargado_remove_cartero_permission.php'));

        $this->assertStringContainsString("'removecartero'", $registry);
        $this->assertStringContainsString("'feature.paquetes-ems.encargado.removecartero' => 'Boton: Quitar cartero'", $registry);
        $this->assertStringContainsString("feature.paquetes-ems.encargado.removecartero", $controller);
        $this->assertStringContainsString("feature.paquetes-ems.encargado.removecartero", $migration);
    }

    public function test_existing_change_cartero_roles_receive_remove_cartero_permission(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_08_12_140000_grant_remove_cartero_to_change_cartero_roles.php'));

        $this->assertStringContainsString('feature.paquetes-ems.encargado.changecartero', $migration);
        $this->assertStringContainsString('feature.paquetes-ems.encargado.removecartero', $migration);
        $this->assertStringContainsString("where('name', 'administrador')", $migration);
        $this->assertStringContainsString("insertOrIgnore", $migration);
    }

    public function test_administrator_always_sees_and_can_use_remove_cartero(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/PaquetesEmsController.php'));

        $this->assertStringContainsString("config('acl.super_admin_role', 'administrador')", $controller);
        $this->assertStringContainsString("'canRemoveCarteroEncargado' => \$isSuperAdmin", $controller);
        $this->assertStringContainsString('if (! $isSuperAdmin)', $controller);
    }
}
