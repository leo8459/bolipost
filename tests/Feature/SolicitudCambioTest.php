<?php

namespace Tests\Feature;

use App\Menu\Filters\MaintenanceAlertBadgeFilter;
use App\Models\SolicitudCambio;
use App\Models\User;
use App\Support\AclPermissionRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SolicitudCambioTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['acl.sync.enabled' => false]);
        // The maintenance badge belongs to a separate module with its own tables.
        config(['adminlte.filters' => array_values(array_filter(config('adminlte.filters'), fn ($filter) => $filter !== MaintenanceAlertBadgeFilter::class))]);
        Schema::create('app_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });
        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->date('fin_contrato')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        Role::findOrCreate('regional', 'web');
        Role::findOrCreate('administrador', 'web');
        (require database_path('migrations/2026_10_22_140000_create_solicitud_cambios_table.php'))->up();
        (require database_path('migrations/2026_10_22_140100_add_solicitudes_cambio_permissions.php'))->up();
    }

    private function user(?string $role = 'regional'): User
    {
        $user = User::create(['name' => 'Solicitante de prueba']);
        if ($role) {
            $user->assignRole($role);
        }

        return $user;
    }

    private function payload(): array
    {
        return ['fecha' => now('America/La_Paz')->toDateString(), 'ciudad' => 'La Paz', 'solicitante' => 'Ana Pérez', 'cargo' => 'Operadora', 'unidad' => 'Admisiones', 'sistema' => 'Bolipost', 'modulo' => 'Paquetes', 'ambiente' => 'Producción', 'tipo' => 'Mejora', 'antecedentes' => 'La búsqueda tarda mucho.', 'detalle' => 'Agregar un filtro por fecha.', 'prioridad' => 'Media'];
    }

    public function test_registra_y_muestra_ficha_sin_permitir_suplantar_propietario(): void
    {
        $user = $this->user();
        $this->actingAs($user)->post(route('solicitudes-cambio.store'), $this->payload() + ['user_id' => 999])->assertSessionHasNoErrors()->assertRedirect();
        $solicitud = SolicitudCambio::sole();
        $this->assertSame($user->id, $solicitud->user_id);
        $this->get(route('solicitudes-cambio.show', $solicitud))->assertOk()->assertSee($solicitud->numero)->assertSee('Firma y sello');
        $this->get(route('solicitudes-cambio.index'))->assertOk()->assertSee('Registrar solicitud');
    }

    public function test_otros_usuarios_no_pueden_leer_fichas_ajenas_y_administrador_si(): void
    {
        $owner = $this->user();
        $solicitud = SolicitudCambio::create($this->payload() + ['user_id' => $owner->id]);
        $this->actingAs($this->user())->getJson(route('solicitudes-cambio.show', $solicitud))->assertForbidden();
        $this->get(route('solicitudes-cambio.index'))->assertOk()->assertDontSee($solicitud->numero);
        $this->actingAs($this->user('administrador'))->get(route('solicitudes-cambio.show', $solicitud))->assertOk();
        $this->get(route('solicitudes-cambio.index'))->assertOk()->assertSee($solicitud->numero);
    }

    public function test_valida_campos_tipo_otro_y_prioridad(): void
    {
        $this->actingAs($this->user())->postJson(route('solicitudes-cambio.store'), [])->assertUnprocessable()->assertJsonValidationErrors(['fecha', 'detalle', 'solicitante']);
        $this->postJson(route('solicitudes-cambio.store'), array_replace($this->payload(), ['tipo' => 'Otro', 'prioridad' => 'Urgente']))->assertUnprocessable()->assertJsonValidationErrors(['otro_tipo', 'prioridad']);
        $this->assertSame(0, SolicitudCambio::count());
        $this->post(route('solicitudes-cambio.store'), array_replace($this->payload(), ['tipo' => 'Otro', 'otro_tipo' => 'Integración']))->assertSessionHasNoErrors();
        $this->assertSame('Integración', SolicitudCambio::sole()->otro_tipo);
    }

    public function test_acceso_y_registro_requieren_permisos_incluso_por_url_directa(): void
    {
        $user = $this->user(null);
        $this->actingAs($user)->getJson(route('solicitudes-cambio.index'))->assertForbidden();
        $this->postJson(route('solicitudes-cambio.store'), $this->payload())->assertForbidden();
        $user->givePermissionTo('solicitudes-cambio.index');
        $this->get(route('solicitudes-cambio.index'))->assertOk()->assertDontSee('id="solicitud-form"', false);
        $this->postJson(route('solicitudes-cambio.store'), $this->payload())->assertForbidden();
    }

    public function test_permisos_del_modulo_se_registran_para_roles(): void
    {
        $permissions = AclPermissionRegistry::allPermissionNames();
        foreach (['solicitudes-cambio.index', 'feature.solicitudes-cambio.create', 'feature.solicitudes-cambio.manage'] as $permission) {
            $this->assertContains($permission, $permissions);
        }
        $this->assertSame(['feature.solicitudes-cambio.create'], AclPermissionRegistry::authorizationPermissionsForRouteAccess('solicitudes-cambio.store'));
        $this->assertFalse(Role::findByName('regional')->hasPermissionTo('feature.solicitudes-cambio.manage'));
        $menu = collect(AclPermissionRegistry::menuPermissionSummary())->firstWhere('label', 'Sistemas');
        $this->assertNotNull($menu);
        $window = collect($menu['children'])->firstWhere('route_name', 'solicitudes-cambio.index');
        $this->assertContains('feature.solicitudes-cambio.create', array_column($window['actions'], 'name'));
        $this->assertContains('feature.solicitudes-cambio.manage', array_column($window['actions'], 'name'));
    }
}
