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
        Schema::dropIfExists('alerta_empresa_destinatarios');
        Schema::dropIfExists('alertas_empresa');
        Schema::dropIfExists('users');
        Schema::dropIfExists('empresa');

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
        $this->assertContains('alertas-empresa.approve', $permissions);
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
