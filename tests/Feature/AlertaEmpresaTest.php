<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureAclPermissionsSynced;
use App\Http\Middleware\EnsureEmpresaContractUsersActive;
use App\Http\Middleware\EnsureRoutePermission;
use App\Http\Middleware\RegistrarAuditoria;
use App\Models\AlertaEmpresa;
use App\Models\Empresa;
use App\Models\User;
use App\Services\AlertaEmpresaService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
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
        Storage::disk('public')->assertExists($alerta->portada_path);
        Storage::disk('public')->assertExists($alerta->pdf_path);
    }

    public function test_company_user_receives_alert_until_marking_it_as_read(): void
    {
        $empresa = $this->createEmpresa();
        $user = User::factory()->create(['empresa_id' => $empresa->id]);
        $alerta = AlertaEmpresa::query()->create([
            'titulo' => 'Alerta pendiente',
            'portada_path' => 'alertas-empresa/portadas/demo.png',
            'publicada_at' => now(),
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
