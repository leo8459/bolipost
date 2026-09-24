<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAclPermissionsSynced;
use App\Http\Middleware\EnsureEmpresaContractUsersActive;
use App\Http\Middleware\EnsureRoutePermission;
use App\Menu\Filters\MaintenanceAlertBadgeFilter;
use App\Menu\Filters\RoutePermissionFilter;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use JeroenNoten\LaravelAdminLte\Menu\Filters\GateFilter;
use Tests\TestCase;

class UserIpsLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('adminlte.filters', array_values(array_filter(
            config('adminlte.filters', []),
            static fn (string $filter): bool => ! in_array($filter, [
                MaintenanceAlertBadgeFilter::class,
                RoutePermissionFilter::class,
                GateFilter::class,
            ], true),
        )));

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('alias')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('ciudad')->nullable();
            $table->json('regionales')->nullable();
            $table->string('provincia_origen')->nullable();
            $table->string('ci')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->unsignedBigInteger('sucursal_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');

            $table->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');

            $table->primary(['permission_id', 'role_id']);
        });

        Schema::create('user_ips_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedSmallInteger('ips_user_pid')->unique();
            $table->string('ips_user_domain', 20)->default('');
            $table->string('ips_user_fid');
            $table->string('ips_user_name');
            $table->unsignedSmallInteger('ips_office_cd')->nullable();
            $table->string('ips_office_fcd', 25)->nullable();
            $table->string('ips_office_name', 64)->nullable();
            $table->boolean('ipsweb')->default(false);
            $table->boolean('restrict_user_offices')->default(false);
            $table->boolean('active')->default(true);
            $table->timestamp('last_verified_at')->nullable();
            $table->json('last_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    private function workbenchUser(): User
    {
        $user = User::factory()->create(['alias' => 'ipsoperator']);
        $user->ipsLink()->create([
            'ips_user_pid' => 73, 'ips_user_domain' => 'WEBCLIENT_NG',
            'ips_user_fid' => 'operator', 'ips_user_name' => 'Operador de prueba',
            'ips_office_cd' => 1, 'ips_office_name' => 'LA PAZ LC/AO', 'active' => true,
        ]);
        config(['services.sitra_ips.base_url' => 'https://sitra.test', 'services.sitra_ips.token' => 'test-token']);
        $this->withoutMiddleware([EnsureAclPermissionsSynced::class, EnsureEmpresaContractUsersActive::class, EnsureRoutePermission::class]);

        return $user;
    }

    public function test_workbench_displays_api_packages_and_total_for_linked_office(): void
    {
        $user = $this->workbenchUser();
        Http::fake(['https://sitra.test/api/v1/ips/paquetes*' => Http::response([
            'data' => [[
                'codigo' => 'TEST123BO', 'local_id' => '', 'recipient' => 'Receptor de prueba',
                'phone' => '', 'city' => 'La Paz', 'address' => '',
                'office_name' => 'LA PAZ LC/AO', 'next_office_name' => '',
                'weight_kg' => 0.1, 'mail_class' => 'U', 'dutiable_ind' => null,
                'stage' => ['tone' => 'info', 'label' => 'Recibido en oficina'],
                'event_at' => '2026-09-14T12:00:00+00:00', 'operational_event_cd' => 32,
            ]],
            'meta' => ['total' => 51],
        ])]);
        $this->actingAs($user)->get(route('ips.index'))->assertOk()
            ->assertSee('TEST123BO')->assertSee('Receptor de prueba')
            ->assertSee('51 resultados')->assertSee('Página 1 de 3')
            ->assertDontSee('Consulta no disponible');
        Http::assertSent(fn (Request $request) => $request['office_cd'] == 1 && $request['per_page'] == 25);
        $partial = $this->getJson(route('ips.index'))->assertOk()->json('html');
        $this->assertStringContainsString('TEST123BO', $partial);
        $this->assertStringNotContainsString('<html', $partial);
        $this->assertStringNotContainsString('Tu bandeja', $partial);
        $this->assertStringNotContainsString('global-facturacion', $partial);
        Http::assertSentCount(2);
    }

    public function test_failed_ips_query_is_not_presented_as_zero_packages(): void
    {
        $user = $this->workbenchUser();
        Http::fake(['https://sitra.test/api/v1/ips/paquetes*' => Http::response(['message' => 'Unavailable'], 503)]);
        $this->actingAs($user)->get(route('ips.index'))->assertOk()
            ->assertSee('Consulta no disponible')->assertSee('Listado no disponible.')
            ->assertDontSee('0 resultados')->assertDontSee('Página 1 de 1');
        $this->getJson(route('ips.index'))->assertStatus(503)->assertJsonMissingPath('html');
    }

    public function test_it_lists_bolipost_users_and_ips_candidates(): void
    {
        $user = User::factory()->create([
            'name' => 'Indira Cecilia Barrios Buitrago',
            'alias' => 'bindira',
            'email' => 'bindira@example.test',
            'ciudad' => 'LA PAZ',
            'regionales' => ['LA PAZ'],
        ]);
        config()->set('services.sitra_ips.base_url', 'https://sitra.test');
        config()->set('services.sitra_ips.token', 'token-sitra');
        Http::fake([
            'https://sitra.test/api/v1/ips/usuarios*' => Http::response([
                'data' => [[
                    'user_pid' => 196,
                    'user_domain' => 'WEBCLIENT_NG',
                    'user_fid' => 'bindira_web',
                    'user_name' => 'Indira Cecilia Barrios Buitrago',
                    'office_name' => 'LA PAZ LC/AO',
                    'ipsweb' => true,
                    'restrict_user_offices' => false,
                ]],
                'meta' => ['has_more' => false],
            ]),
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([EnsureAclPermissionsSynced::class, EnsureEmpresaContractUsersActive::class, EnsureRoutePermission::class])
            ->get(route('users.ips-links.index', ['q' => 'bindira']))
            ->assertOk()
            ->assertSee('Indira Cecilia Barrios Buitrago')
            ->assertSee('WEBCLIENT_NG\\bindira_web')
            ->assertSee('PID IPS');

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/api/v1/ips/usuarios')
            && $request->hasHeader('Authorization', 'Bearer token-sitra'));
    }

    public function test_it_stores_verified_ips_link(): void
    {
        $user = User::factory()->create(['alias' => 'bindira']);
        config()->set('services.sitra_ips.base_url', 'https://sitra.test');
        config()->set('services.sitra_ips.token', 'token-sitra');
        Http::fake([
            'https://sitra.test/api/v1/ips/usuarios/196' => Http::response([
                'data' => [
                    'user_pid' => 196,
                    'user_domain' => 'WEBCLIENT_NG',
                    'user_fid' => 'bindira_web',
                    'user_name' => 'Indira Cecilia Barrios Buitrago',
                    'office_cd' => 1,
                    'office_fcd' => 'BOLPBA',
                    'office_name' => 'LA PAZ LC/AO',
                    'ipsweb' => true,
                    'restrict_user_offices' => false,
                ],
            ]),
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([EnsureAclPermissionsSynced::class, EnsureEmpresaContractUsersActive::class, EnsureRoutePermission::class])
            ->post(route('users.ips-links.store', $user), ['ips_user_pid' => 196])
            ->assertRedirect();

        $this->assertDatabaseHas('user_ips_links', [
            'user_id' => $user->id,
            'ips_user_pid' => 196,
            'ips_user_domain' => 'WEBCLIENT_NG',
            'ips_user_fid' => 'bindira_web',
            'ips_office_cd' => 1,
            'active' => true,
        ]);
    }

    public function test_it_creates_ips_user_and_links_it(): void
    {
        $user = User::factory()->create([
            'name' => 'Indira Cecilia Barrios Buitrago',
            'alias' => 'bindira',
            'email' => 'bindira@example.test',
        ]);
        config()->set('services.sitra_ips.base_url', 'https://sitra.test');
        config()->set('services.sitra_ips.token', 'token-sitra');
        Http::fake([
            'https://sitra.test/api/v1/ips/usuarios' => Http::response([
                'data' => [
                    'user_pid' => 250,
                    'user_domain' => 'AGBC',
                    'user_fid' => 'bindira',
                    'user_name' => 'Indira Cecilia Barrios Buitrago',
                    'office_cd' => null,
                    'office_fcd' => '',
                    'office_name' => '',
                    'ipsweb' => false,
                    'restrict_user_offices' => false,
                ],
            ], 201),
        ]);

        $this->actingAs($user)
            ->withoutMiddleware([EnsureAclPermissionsSynced::class, EnsureEmpresaContractUsersActive::class, EnsureRoutePermission::class])
            ->post(route('users.ips-links.create-user', $user), [
                'user_domain' => 'agbc',
                'user_fid' => 'bindira',
                'user_name' => 'Indira Cecilia Barrios Buitrago',
                'email' => 'bindira@example.test',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('user_ips_links', [
            'user_id' => $user->id,
            'ips_user_pid' => 250,
            'ips_user_domain' => 'AGBC',
            'ips_user_fid' => 'bindira',
        ]);

        Http::assertSent(fn (Request $request) => $request->method() === 'POST'
            && $request->url() === 'https://sitra.test/api/v1/ips/usuarios'
            && $request['user_domain'] === 'AGBC'
            && $request['user_fid'] === 'bindira'
            && $request->hasHeader('Authorization', 'Bearer token-sitra'));
    }
}
