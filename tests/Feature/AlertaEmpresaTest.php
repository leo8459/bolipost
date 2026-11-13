<?php

namespace Tests\Feature;

use App\Http\Controllers\AlertaEmpresaController;
use App\Http\Middleware\EnsureAclPermissionsSynced;
use App\Http\Middleware\EnsureEmpresaContractUsersActive;
use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\RegistrarAuditoria;
use App\Models\AlertaEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Services\AlertaEmpresaService;
use App\Support\AclPermissionRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AlertaEmpresaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureAclPermissionsSynced::class,
            EnsureEmpresaContractUsersActive::class,
            EnsureRoutePermission::class,
            RegistrarAuditoria::class,
        ]);

        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();

        Schema::create('empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('sigla');
            $table->string('codigo_cliente');
            $table->timestamps();
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('alias')->unique();
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('alertas_empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('titulo');
            $table->text('mensaje')->nullable();
            $table->string('portada_path');
            $table->string('pdf_path')->nullable();
            $table->unsignedBigInteger('creado_por')->nullable();
            $table->timestamp('publicada_at');
            $table->timestamp('aprobada_at')->nullable();
            $table->unsignedBigInteger('aprobada_por')->nullable();
            $table->timestamp('vence_at')->nullable();
            $table->timestamps();
        });
        Schema::create('alerta_empresa_destinatarios', function (Blueprint $table): void {
            $table->unsignedBigInteger('alerta_empresa_id');
            $table->unsignedBigInteger('empresa_id');
            $table->primary(['alerta_empresa_id', 'empresa_id']);
        });
        Schema::create('alerta_empresa_usuarios', function (Blueprint $table): void {
            $table->unsignedBigInteger('alerta_empresa_id');
            $table->unsignedBigInteger('user_id');
            $table->primary(['alerta_empresa_id', 'user_id']);
        });
        Schema::create('alerta_empresa_lecturas', function (Blueprint $table): void {
            $table->unsignedBigInteger('alerta_empresa_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('leida_at');
            $table->primary(['alerta_empresa_id', 'user_id']);
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('alerta_empresa_lecturas');
        Schema::dropIfExists('alerta_empresa_usuarios');
        Schema::dropIfExists('alerta_empresa_destinatarios');
        Schema::dropIfExists('alertas_empresa');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresa');
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->down();

        parent::tearDown();
    }

    public function test_administrator_can_send_an_alert_with_cover_and_optional_pdf(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $empresa = $this->createEmpresa();

        $response = $this->actingAs($admin)->post(route('alertas-empresa.store'), [
            'titulo' => 'Nuevo comunicado',
            'mensaje' => 'Texto para el perfil de empresa.',
            'empresa_ids' => [$empresa->id],
            'portada' => $this->fakePng(),
            'pdf' => UploadedFile::fake()->create('comunicado.pdf', 120, 'application/pdf'),
        ]);

        $response->assertRedirect(route('alertas-empresa.index'));
        $alerta = AlertaEmpresa::query()->firstOrFail();
        $this->assertTrue($alerta->empresas->contains($empresa));
        $this->assertNull($alerta->aprobada_at);
        $this->assertNull(app(AlertaEmpresaService::class)->siguienteNoLeida(
            User::factory()->create(['empresa_id' => $empresa->id])
        ));
        Storage::disk('public')->assertExists($alerta->portada_path);
        Storage::disk('public')->assertExists($alerta->pdf_path);
    }

    public function test_administrator_can_correct_and_approve_a_pending_alert(): void
    {
        $admin = User::factory()->create();
        $empresa = $this->createEmpresa();
        $recipient = User::factory()->create(['empresa_id' => $empresa->id]);
        $alerta = AlertaEmpresa::query()->create([
            'titulo' => 'Titlo con error',
            'mensaje' => 'Mensage original',
            'portada_path' => 'alertas-empresa/portadas/demo.png',
            'publicada_at' => now()->subHour(),
        ]);
        $alerta->empresas()->attach($empresa);

        $this->assertNull(app(AlertaEmpresaService::class)->siguienteNoLeida($recipient));
        $this->actingAs($recipient)
            ->post(route('alertas-empresa.read', $alerta))
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch(route('alertas-empresa.approve', $alerta), [
                '_approval_alert_id' => $alerta->id,
                'titulo' => 'Título corregido',
                'mensaje' => 'Mensaje corregido y listo.',
            ])
            ->assertRedirect(route('alertas-empresa.index'));

        $alerta->refresh();
        $this->assertSame('Título corregido', $alerta->titulo);
        $this->assertNotNull($alerta->aprobada_at);
        $this->assertSame($admin->id, $alerta->aprobada_por);
        $this->assertSame($alerta->id, app(AlertaEmpresaService::class)->siguienteNoLeida($recipient)?->id);
    }

    public function test_company_user_receives_alert_until_marking_it_as_read(): void
    {
        $empresa = $this->createEmpresa();
        $user = User::factory()->create(['empresa_id' => $empresa->id]);
        $alerta = AlertaEmpresa::query()->create([
            'titulo' => 'Alerta pendiente',
            'portada_path' => 'alertas-empresa/portadas/demo.png',
            'publicada_at' => now(),
            'aprobada_at' => now(),
        ]);
        $alerta->empresas()->attach($empresa);

        $this->assertSame($alerta->id, app(AlertaEmpresaService::class)->siguienteNoLeida($user)?->id);

        $this->actingAs($user)
            ->post(route('alertas-empresa.read', $alerta))
            ->assertRedirect();

        $this->assertDatabaseHas('alerta_empresa_lecturas', [
            'alerta_empresa_id' => $alerta->id,
            'user_id' => $user->id,
        ]);
        $this->assertNull(app(AlertaEmpresaService::class)->siguienteNoLeida($user));
    }

    public function test_administrator_can_send_an_alert_to_a_user_without_company(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $recipient = User::factory()->create(['empresa_id' => null]);

        $this->actingAs($admin)->post(route('alertas-empresa.store'), [
            'titulo' => 'Comunicado interno',
            'mensaje' => 'Texto para un usuario de correo.',
            'user_ids' => [$recipient->id],
            'portada' => $this->fakePng(),
        ])->assertRedirect(route('alertas-empresa.index'));

        $alerta = AlertaEmpresa::query()->firstOrFail();
        $this->assertTrue($alerta->usuariosDestinatarios->contains($recipient));
        $this->assertTrue($alerta->empresas->isEmpty());
        $this->assertNull($alerta->aprobada_at);
    }

    public function test_user_without_company_receives_individual_alert_until_marking_it_as_read(): void
    {
        $recipient = User::factory()->create(['empresa_id' => null]);
        $otherUser = User::factory()->create(['empresa_id' => null]);
        $alerta = AlertaEmpresa::query()->create([
            'titulo' => 'Alerta por correo',
            'portada_path' => 'alertas-empresa/portadas/demo.png',
            'publicada_at' => now(),
            'aprobada_at' => now(),
        ]);
        $alerta->usuariosDestinatarios()->attach($recipient);

        $this->assertSame($alerta->id, app(AlertaEmpresaService::class)->siguienteNoLeida($recipient)?->id);
        $this->assertNull(app(AlertaEmpresaService::class)->siguienteNoLeida($otherUser));

        $this->actingAs($otherUser)
            ->post(route('alertas-empresa.read', $alerta))
            ->assertForbidden();

        $this->actingAs($recipient)
            ->post(route('alertas-empresa.read', $alerta))
            ->assertRedirect();

        $this->assertDatabaseHas('alerta_empresa_lecturas', [
            'alerta_empresa_id' => $alerta->id,
            'user_id' => $recipient->id,
        ]);
        $this->assertNull(app(AlertaEmpresaService::class)->siguienteNoLeida($recipient));
    }

    public function test_company_user_cannot_be_selected_as_individual_recipient(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create();
        $empresa = $this->createEmpresa();
        $companyUser = User::factory()->create(['empresa_id' => $empresa->id]);

        $this->actingAs($admin)->post(route('alertas-empresa.store'), [
            'titulo' => 'Destino inválido',
            'user_ids' => [$companyUser->id],
            'portada' => $this->fakePng(),
        ])->assertSessionHasErrorsIn('createAlert', 'user_ids.0');

        $this->assertDatabaseCount('alertas_empresa', 0);
    }

    public function test_empresa_role_only_lists_approved_alerts_for_its_own_company(): void
    {
        $ownEmpresa = $this->createEmpresa();
        $otherEmpresa = Empresa::query()->create([
            'nombre' => 'OTRA EMPRESA',
            'sigla' => 'OE',
            'codigo_cliente' => 'OE001',
        ]);
        $user = User::factory()->create(['empresa_id' => $ownEmpresa->id]);
        $empresaRole = new Role(['name' => 'empresa', 'guard_name' => 'web']);
        $user->setRelation('roles', collect([$empresaRole]));

        $ownAlert = AlertaEmpresa::query()->create([
            'titulo' => 'Alerta propia aprobada',
            'portada_path' => 'propia.png',
            'publicada_at' => now(),
            'aprobada_at' => now(),
        ]);
        $ownAlert->empresas()->attach([$ownEmpresa->id, $otherEmpresa->id]);

        $otherAlert = AlertaEmpresa::query()->create([
            'titulo' => 'Alerta de otra empresa',
            'portada_path' => 'otra.png',
            'publicada_at' => now(),
            'aprobada_at' => now(),
        ]);
        $otherAlert->empresas()->attach($otherEmpresa);

        $pendingAlert = AlertaEmpresa::query()->create([
            'titulo' => 'Alerta propia pendiente',
            'portada_path' => 'pendiente.png',
            'publicada_at' => now(),
        ]);
        $pendingAlert->empresas()->attach($ownEmpresa);

        $request = Request::create(route('alertas-empresa.index'), 'GET');
        $request->setUserResolver(fn () => $user);
        $view = app(AlertaEmpresaController::class)->index($request);
        $alertas = $view->getData()['alertas'];

        $this->assertSame([$ownAlert->id], $alertas->pluck('id')->all());
        $this->assertSame([$ownEmpresa->id], $alertas->first()->empresas->pluck('id')->all());
        $this->assertTrue($view->getData()['isEmpresaUser']);

        $this->actingAs($user)
            ->get(route('alertas-empresa.portada', $otherAlert))
            ->assertForbidden();
    }

    public function test_new_alert_buttons_are_available_for_role_customization(): void
    {
        $permissions = AclPermissionRegistry::allPermissionNames();

        $this->assertContains('feature.alertas-empresa.approve', $permissions);
        $this->assertContains('feature.alertas-empresa.readers', $permissions);
        $this->assertContains('feature.alertas-empresa.print-readers', $permissions);
        $this->assertContains('alertas-empresa.approve', $permissions);
    }

    public function test_views_report_downloads_pdf_grouped_by_company_and_sorted_by_user(): void
    {
        $admin = $this->createReportUser();
        $salud = Empresa::query()->create([
            'nombre' => 'MINISTERIO DE SALUD', 'sigla' => 'MS', 'codigo_cliente' => 'MS001',
        ]);
        $gestora = Empresa::query()->create([
            'nombre' => 'GESTORA', 'sigla' => 'GP', 'codigo_cliente' => 'GP001',
        ]);
        $zulema = User::factory()->create(['name' => 'Zulema Salud', 'empresa_id' => $salud->id]);
        $beatriz = User::factory()->create(['name' => 'Beatriz Gestora', 'empresa_id' => $gestora->id]);
        $alvaro = User::factory()->create(['name' => 'Álvaro Gestora', 'empresa_id' => $gestora->id]);
        $individual = User::factory()->create(['name' => 'Ana Individual', 'empresa_id' => null]);
        $unread = User::factory()->create(['name' => 'Usuario sin lectura', 'empresa_id' => $gestora->id]);
        $alerta = $this->createPublishedAlert();
        $alerta->empresas()->attach([$salud->id, $gestora->id]);
        $alerta->usuariosDestinatarios()->attach($individual);
        $alerta->lectores()->attach([$zulema->id, $beatriz->id, $individual->id, $alvaro->id], [
            'leida_at' => '2026-11-13 09:30:00',
        ]);
        $otherAlert = $this->createPublishedAlert();
        $otherAlert->lectores()->attach($unread, ['leida_at' => now()]);

        $response = $this->actingAs($admin)->get(route('alertas-empresa.reporte-vistas', $alerta));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringContainsString('reporte-vistas-alerta-'.$alerta->id.'.pdf', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $text = (new Parser)->parseContent($response->getContent())->getText();
        $previousPosition = -1;
        foreach (['GESTORA (GP)', 'Álvaro Gestora', 'Beatriz Gestora', 'MINISTERIO DE SALUD (MS)', 'Zulema Salud', 'Sin empresa', 'Ana Individual'] as $expected) {
            $position = mb_strpos($text, $expected);
            $this->assertNotFalse($position, $expected.' no aparece en el PDF.');
            $this->assertGreaterThan($previousPosition, $position, $expected.' está fuera de orden.');
            $previousPosition = $position;
        }
        $this->assertStringContainsString('13/11/2026 09:30', $text);
        $this->assertStringNotContainsString($unread->name, $text);
    }

    public function test_views_report_handles_an_alert_without_readers(): void
    {
        $response = $this->actingAs($this->createReportUser())
            ->get(route('alertas-empresa.reporte-vistas', $this->createPublishedAlert()));

        $response->assertOk()->assertHeader('content-type', 'application/pdf');
        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('Ningún usuario marcó esta alerta como vista', $text);
    }

    public function test_reader_permission_alone_does_not_allow_printing_the_report(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo(Permission::findOrCreate('feature.alertas-empresa.readers', 'web'));

        $this->actingAs($user)
            ->get(route('alertas-empresa.reporte-vistas', $this->createPublishedAlert()))
            ->assertForbidden();
    }

    public function test_report_permission_appears_in_roles_under_the_alerts_window(): void
    {
        AclPermissionRegistry::syncPermissions();
        $groups = AclPermissionRegistry::groupedPermissionsForMatrix();
        $group = collect($groups)->firstWhere('module_key', 'alertas-empresa');
        $permission = collect($group['permissions'])->firstWhere('name', 'feature.alertas-empresa.print-readers');

        $this->assertSame('Boton: Imprimir reporte de vistas (PDF)', $permission['action_label']);

        $findWindow = function (array $nodes) use (&$findWindow): ?array {
            foreach ($nodes as $node) {
                if (($node['route_name'] ?? null) === 'alertas-empresa.index') {
                    return $node;
                }
                if ($window = $findWindow($node['children'] ?? [])) {
                    return $window;
                }
            }

            return null;
        };
        $window = $findWindow(AclPermissionRegistry::menuPermissionSummary($groups));
        $this->assertNotNull($window);
        $this->assertContains('feature.alertas-empresa.print-readers', array_column($window['actions'], 'name'));
    }

    public function test_report_permission_can_be_granted_and_revoked_through_roles(): void
    {
        $admin = User::factory()->create();
        $user = User::factory()->create();
        $role = Role::create(['name' => 'Responsable de alertas', 'guard_name' => 'web']);
        $user->assignRole($role);
        $alerta = $this->createPublishedAlert();

        $this->actingAs($admin)->put(route('roles.update', $role), [
            'name' => $role->name,
            'permissions' => ['feature.alertas-empresa.readers', 'feature.alertas-empresa.print-readers'],
        ])->assertRedirect(route('roles.index'));

        $this->actingAs($user->fresh())->get(route('alertas-empresa.reporte-vistas', $alerta))->assertOk();

        $this->actingAs($admin)->put(route('roles.update', $role), [
            'name' => $role->name,
            'permissions' => ['feature.alertas-empresa.readers'],
        ])->assertRedirect(route('roles.index'));

        $user = $user->fresh();
        $this->assertTrue($user->can('feature.alertas-empresa.readers'));
        $this->actingAs($user)->get(route('alertas-empresa.reporte-vistas', $alerta))->assertForbidden();
    }

    public function test_report_permission_migration_only_grants_it_to_the_administrator(): void
    {
        $adminRole = Role::create(['name' => 'administrador', 'guard_name' => 'web']);
        $companyRole = Role::create(['name' => 'empresa', 'guard_name' => 'web']);
        $companyRole->givePermissionTo(Permission::findOrCreate('feature.alertas-empresa.readers', 'web'));
        $migration = require database_path('migrations/2026_11_13_120000_add_alertas_empresa_print_readers_permission.php');

        $migration->up();
        $migration->up();

        $this->assertTrue($adminRole->fresh()->hasPermissionTo('feature.alertas-empresa.print-readers'));
        $this->assertFalse($companyRole->fresh()->hasPermissionTo('feature.alertas-empresa.print-readers'));

        $migration->down();
        $this->assertFalse(Permission::where('name', 'feature.alertas-empresa.print-readers')->exists());
        $this->assertTrue($companyRole->fresh()->hasPermissionTo('feature.alertas-empresa.readers'));
    }

    public function test_views_report_repeats_company_headers_across_pages(): void
    {
        $empresa = $this->createEmpresa();
        $lectores = User::factory()->count(70)->sequence(fn ($sequence) => [
            'name' => sprintf('Lector %03d', $sequence->index + 1),
            'empresa_id' => $empresa->id,
        ])->create();
        $alerta = $this->createPublishedAlert();
        $alerta->empresas()->attach($empresa);
        $alerta->lectores()->attach($lectores->modelKeys(), ['leida_at' => now()]);

        $response = $this->actingAs($this->createReportUser())
            ->get(route('alertas-empresa.reporte-vistas', $alerta));

        $response->assertOk();
        $document = (new Parser)->parseContent($response->getContent());
        $this->assertGreaterThan(1, count($document->getPages()));
        foreach ($document->getPages() as $page) {
            $this->assertStringContainsString('EMPRESA DEMO (ED)', $page->getText());
        }
        $this->assertStringContainsString('Lector 070', $document->getText());
    }

    public function test_views_report_is_not_available_for_pending_alerts(): void
    {
        $alerta = $this->createPublishedAlert();
        $alerta->update(['aprobada_at' => null]);

        $this->actingAs($this->createReportUser())
            ->get(route('alertas-empresa.reporte-vistas', $alerta))
            ->assertNotFound();
    }

    public function test_company_views_report_only_includes_its_own_readers(): void
    {
        $ownEmpresa = $this->createEmpresa();
        $otherEmpresa = Empresa::query()->create([
            'nombre' => 'OTRA EMPRESA', 'sigla' => 'OE', 'codigo_cliente' => 'OE001',
        ]);
        $user = $this->createReportUser(['name' => 'Lector propio', 'empresa_id' => $ownEmpresa->id]);
        $user->setRelation('roles', collect([new Role(['name' => 'empresa', 'guard_name' => 'web'])]));
        $otherUser = User::factory()->create(['name' => 'Lector ajeno', 'empresa_id' => $otherEmpresa->id]);
        $alerta = $this->createPublishedAlert();
        $alerta->empresas()->attach([$ownEmpresa->id, $otherEmpresa->id]);
        $alerta->lectores()->attach([$user->id, $otherUser->id], ['leida_at' => now()]);

        $response = $this->actingAs($user)->get(route('alertas-empresa.reporte-vistas', $alerta));

        $response->assertOk();
        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringContainsString($user->name, $text);
        $this->assertStringNotContainsString($otherUser->name, $text);
        $this->assertStringNotContainsString($otherEmpresa->nombre, $text);

        $otherAlert = $this->createPublishedAlert();
        $otherAlert->empresas()->attach($otherEmpresa);
        $this->get(route('alertas-empresa.reporte-vistas', $otherAlert))->assertForbidden();
    }

    private function createReportUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->givePermissionTo(Permission::findOrCreate('feature.alertas-empresa.print-readers', 'web'));

        return $user;
    }

    private function createPublishedAlert(): AlertaEmpresa
    {
        return AlertaEmpresa::query()->create([
            'titulo' => 'Comunicado publicado',
            'mensaje' => 'Información de la alerta enviada.',
            'portada_path' => 'alertas-empresa/portadas/demo.png',
            'publicada_at' => now(),
            'aprobada_at' => now(),
        ]);
    }

    private function createEmpresa(): Empresa
    {
        return Empresa::query()->create([
            'nombre' => 'EMPRESA DEMO',
            'sigla' => 'ED',
            'codigo_cliente' => 'ED001',
        ]);
    }

    private function fakePng(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true);

        return UploadedFile::fake()->createWithContent('portada.png', $png);
    }
}
