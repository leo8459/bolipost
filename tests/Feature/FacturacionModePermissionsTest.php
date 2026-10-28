<?php

namespace Tests\Feature;

use App\Models\AppSetting;
use App\Models\User;
use App\Services\FacturacionCartService;
use App\Services\FacturacionClienteFrecuenteService;
use App\Http\Controllers\RoleController;
use App\Support\AclPermissionRegistry;
use App\Support\FacturacionModePolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FacturacionModePermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMinimalPermissionSchema();

        AclPermissionRegistry::syncPermissions('web');
    }

    public function test_legacy_billing_role_opens_with_all_three_methods_checked(): void
    {
        $role = $this->createBillingRole('Caja legado', [
            'feature.dashboard.facturacion',
        ]);
        $view = (new RoleController())->edit($role->id);
        $selectedPermissions = $view->getData()['selectedPermissions'];
        $formData = $view->getData();
        $formData['errors'] = new ViewErrorBag();
        $formHtml = view('role.form', $formData)->render();
        foreach (FacturacionModePolicy::modePermissionNames() as $permission) {
            $this->assertContains($permission, $selectedPermissions, 'El permiso '.$permission.' debe aparecer marcado para un rol legado.');
            $this->assertMatchesRegularExpression(
                '/<input\\b(?=[^>]*value="'.preg_quote($permission, '/').'"(?=\\s))(?=[^>]*checked)[^>]*>/s',
                $formHtml,
                'El checkbox '.$permission.' debe quedar marcado en el formulario legado.'
            );
        }
    }

    public function test_role_save_persists_only_the_selected_methods_and_marks_configuration_explicit(): void
    {
        $role = $this->createBillingRole('Caja restringida', [
            'feature.dashboard.facturacion',
        ]);

        $request = Request::create('/role/'.$role->id, 'PUT', [
            'name' => $role->name,
            'facturacion_modes_configured' => '1',
            'permissions' => [
                'feature.dashboard.facturacion',
                'facturacion.mode.qr_solo',
            ],
        ]);

        (new RoleController())->update($request, $role);
        $role->refresh();
        $this->assertEqualsCanonicalizing([
            'feature.dashboard.facturacion',
            'facturacion.mode.qr_solo',
            FacturacionModePolicy::CONFIGURED_PERMISSION,
        ], $role->permissions()->pluck('name')->all());

        $editView = (new RoleController())->edit($role->id);
        $formData = $editView->getData();
        $formData['errors'] = new ViewErrorBag();
        $formHtml = view('role.form', $formData)->render();
        foreach (FacturacionModePolicy::modePermissionNames() as $permission) {
            $pattern = '/<input\\b(?=[^>]*value="'.preg_quote($permission, '/').'"(?=\\s))(?=[^>]*checked)[^>]*>/s';
            if ($permission === 'facturacion.mode.qr_solo') {
                $this->assertMatchesRegularExpression($pattern, $formHtml);
            } else {
                $this->assertDoesNotMatchRegularExpression($pattern, $formHtml);
            }
        }
    }

    public function test_configured_role_cannot_emit_cash_or_qr_with_invoice_when_only_qr_payment_is_granted(): void
    {
        $user = $this->userWithModes(['qr_solo']);
        $service = Mockery::mock(FacturacionCartService::class);
        $service->shouldNotReceive('updateDraftBillingData');
        $service->shouldNotReceive('getRemoteContextForUser');
        $service->shouldNotReceive('emitirBorrador');
        $this->instance(FacturacionCartService::class, $service);
        $this->instance(FacturacionClienteFrecuenteService::class, Mockery::mock(FacturacionClienteFrecuenteService::class));

        $basePayload = [
            'modalidad_facturacion' => 'con_datos',
            'numero_documento' => '123456',
            'razon_social' => 'Cliente de prueba',
        ];

        $this->actingAs($user)
            ->postJson(route('facturacion.cart.emitir'), $basePayload + [
                'canal_emision' => 'factura_electronica',
                'auto_emit_invoice' => '1',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('canal_emision');

        $this->postJson(route('facturacion.cart.emitir'), $basePayload + [
            'canal_emision' => 'qr',
            'auto_emit_invoice' => '1',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('canal_emision');
    }

    public function test_qr_only_permission_allows_qr_without_invoice_and_poll_cannot_upgrade_it(): void
    {
        $user = $this->userWithModes(['qr_solo']);
        $service = Mockery::mock(FacturacionCartService::class);
        $service->shouldReceive('updateDraftBillingData')->once()->with($user, Mockery::on(
            fn (array $snapshot): bool => ($snapshot['canal_emision'] ?? null) === 'qr'
        ))->andReturn(null);
        $service->shouldReceive('getRemoteContextForUser')->once()->with($user)->andReturn([
            'draft' => (object) ['items' => [(object) ['id' => 1]]],
        ]);
        $service->shouldReceive('emitirBorrador')->once()->with($user, Mockery::on(
            fn (array $snapshot): bool => ($snapshot['canal_emision'] ?? null) === 'qr'
                && ($snapshot['auto_emit_invoice'] ?? null) === null
        ))->andThrow(new \RuntimeException('Prueba detenida antes de una emision real.'));
        $service->shouldReceive('consultarEstadoEmision')->once()->with($user, 57, false, false)->andReturn([
            'carrito' => null,
            'respuesta' => [],
        ]);
        $frequentClients = Mockery::mock(FacturacionClienteFrecuenteService::class);
        $frequentClients->shouldReceive('rememberFacturadaCart')->once()->with(null);

        $this->instance(FacturacionCartService::class, $service);
        $this->instance(FacturacionClienteFrecuenteService::class, $frequentClients);

        $this->actingAs($user)
            ->postJson(route('facturacion.cart.emitir'), [
                'modalidad_facturacion' => 'con_datos',
                'canal_emision' => 'qr',
                'auto_emit_invoice' => '0',
                'numero_documento' => '123456',
                'razon_social' => 'Cliente de prueba',
            ])
            ->assertUnprocessable()
            ->assertJsonMissingValidationErrors('canal_emision');

        $this->postJson(route('facturacion.cart.consultar'), [
            'cart_id' => 57,
            'auto_emit_invoice' => '1',
        ])
            ->assertOk()
            ->assertJsonPath('auto_emit_invoice', '0');
    }

    public function test_global_setting_can_disable_a_role_granted_method(): void
    {
        $user = $this->userWithModes(['qr_solo']);
        AppSetting::setValue('facturacion.show_qr_solo', '0');

        $this->assertFalse(app(FacturacionModePolicy::class)->allows($user, 'qr_solo'));
    }

    public function test_each_explicit_method_permission_is_independent(): void
    {
        $policy = app(FacturacionModePolicy::class);

        foreach (array_keys(FacturacionModePolicy::MODE_PERMISSIONS) as $grantedMode) {
            $user = $this->userWithModes([$grantedMode]);

            foreach (array_keys(FacturacionModePolicy::MODE_PERMISSIONS) as $checkedMode) {
                $this->assertSame(
                    $grantedMode === $checkedMode,
                    $policy->allows($user, $checkedMode),
                    'El rol con '.$grantedMode.' no debe habilitar '.$checkedMode.'.'
                );
            }
        }
    }

    public function test_global_qr_invoice_switch_prevents_auto_invoice_during_status_poll(): void
    {
        $user = $this->userWithModes(['qr_factura']);
        AppSetting::setValue('facturacion.show_qr_factura', '0');

        $service = Mockery::mock(FacturacionCartService::class);
        $service->shouldReceive('consultarEstadoEmision')->once()->with($user, 57, false, false)->andReturn([
            'carrito' => null,
            'respuesta' => [],
        ]);
        $frequentClients = Mockery::mock(FacturacionClienteFrecuenteService::class);
        $frequentClients->shouldReceive('rememberFacturadaCart')->once()->with(null);
        $this->instance(FacturacionCartService::class, $service);
        $this->instance(FacturacionClienteFrecuenteService::class, $frequentClients);

        $this->actingAs($user)
            ->postJson(route('facturacion.cart.consultar'), [
                'cart_id' => 57,
                'auto_emit_invoice' => '1',
            ])
            ->assertOk()
            ->assertJsonPath('auto_emit_invoice', '0');
    }

    public function test_global_qr_invoice_switch_also_applies_when_reopening_a_qr_viewer(): void
    {
        $user = $this->userWithModes(['qr_factura']);
        AppSetting::setValue('facturacion.show_qr_factura', '0');
        $cart = (object) [
            'id' => 57,
            'codigo_orden' => 'ORD-57',
            'canal_emision' => 'qr',
            'total' => 25,
            'items' => [],
        ];

        $service = Mockery::mock(FacturacionCartService::class);
        $service->shouldReceive('consultarEstadoEmision')->once()->with($user, 57, false, false)->andReturn([
            'carrito' => $cart,
            'respuesta' => ['transaction_id' => 'TX-57'],
        ]);
        $frequentClients = Mockery::mock(FacturacionClienteFrecuenteService::class);
        $this->instance(FacturacionCartService::class, $service);
        $this->instance(FacturacionClienteFrecuenteService::class, $frequentClients);

        $this->actingAs($user)
            ->postJson(route('facturacion.cart.ver-qr'), ['cart_id' => 57])
            ->assertOk()
            ->assertJsonPath('cart.auto_emit_invoice', '0');
    }

    /** @param array<int, string> $permissions */
    private function createBillingRole(string $name, array $permissions): Role
    {
        $role = Role::create(['name' => $name, 'guard_name' => 'web']);
        $role->syncPermissions($permissions);

        return $role;
    }

    /** @param array<int, string> $modes */
    private function userWithModes(array $modes): User
    {
        $role = Role::create(['name' => 'Caja '.uniqid(), 'guard_name' => 'web']);
        $role->syncPermissions([
            'feature.dashboard.facturacion',
            FacturacionModePolicy::CONFIGURED_PERMISSION,
            ...array_map(fn (string $mode): string => FacturacionModePolicy::MODE_PERMISSIONS[$mode], $modes),
        ]);

        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function createMinimalPermissionSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->string('remember_token')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }
}
