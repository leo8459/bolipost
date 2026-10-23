<?php

namespace Tests\Feature;

use App\Http\Controllers\RecojoController;
use App\Http\Controllers\ServicioAdicionalController;
use App\Models\Empresa;
use App\Models\Recojo;
use App\Models\User;
use App\Services\ContratoCodigoService;
use App\Support\AclPermissionRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ServicioAdicionalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_10_22_130000_add_servicio_ida_vuelta_to_empresa_table.php'))->up();
        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'Empresa Uno'],
            ['id' => 2, 'nombre' => 'Empresa Dos'],
        ]);
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 10;
        $user->empresa_id = 1;
        $user->ciudad = 'LA PAZ';
        $user->shouldReceive('can')->andReturnUsing(fn ($permission) => in_array($permission, $permissions, true));

        return $user;
    }

    private function request(array $data, array $permissions = []): Request
    {
        $request = Request::create('/', 'POST', $data);
        $request->setUserResolver(fn () => $this->userWithPermissions($permissions));

        return $request;
    }

    public function test_habilita_y_deshabilita_solo_la_empresa_seleccionada(): void
    {
        $controller = new ServicioAdicionalController;
        foreach ([true, false] as $enabled) {
            $controller->update($this->request(['servicio_ida_vuelta' => (int) $enabled], ['feature.servicios-adicionales.edit']), Empresa::findOrFail(1));
            $this->assertSame($enabled, Empresa::findOrFail(1)->servicio_ida_vuelta);
            $this->assertFalse(Empresa::findOrFail(2)->servicio_ida_vuelta);
        }
    }

    public function test_permiso_de_lectura_no_permite_modificar(): void
    {
        $this->expectException(HttpException::class);
        try {
            (new ServicioAdicionalController)->update($this->request(['servicio_ida_vuelta' => 1], ['servicios-adicionales.index']), Empresa::findOrFail(1));
        } finally {
            $this->assertFalse(Empresa::findOrFail(1)->servicio_ida_vuelta);
        }
    }

    public function test_no_permite_ver_la_pantalla_sin_permiso(): void
    {
        try {
            (new ServicioAdicionalController)->index($this->request([]));
            $this->fail('Debe rechazar el acceso.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_busca_empresas_y_filtra_por_estado(): void
    {
        DB::table('empresa')->where('id', 2)->update(['servicio_ida_vuelta' => true]);
        $view = (new ServicioAdicionalController)->index($this->request(['q' => 'DOS', 'estado' => 'habilitado'], ['servicios-adicionales.index']));
        $this->assertSame([2], $view->getData()['empresas']->pluck('id')->all());
    }

    public function test_el_formulario_refleja_la_habilitacion_de_la_empresa(): void
    {
        $this->actingAs($this->userWithPermissions([]));
        $controller = app(RecojoController::class);
        $this->assertFalse($controller->create()->getData()['servicioIdaVueltaHabilitado']);
        DB::table('empresa')->where('id', 1)->update(['servicio_ida_vuelta' => true]);
        $this->assertTrue($controller->create()->getData()['servicioIdaVueltaHabilitado']);
    }

    private function shipmentRequest(bool $returnTrip): Request
    {
        $this->actingAs($this->userWithPermissions(['feature.paquetes-contrato.create.create']));
        $request = $this->request([
            'nombre_r' => 'Remitente', 'telefono_r' => '77777777', 'direccion_r' => 'Origen',
            'nombre_d' => 'Destinatario', 'telefono_d' => '66666666', 'direccion' => 'Destino',
            'destino' => 'COCHABAMBA', 'contenido' => 'Documentos', 'cantidad' => 1,
            'tipo_envio' => 'departamental', 'ida_vuelta' => (int) $returnTrip,
            'empresa_id' => 2,
        ]);
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    public function test_rechaza_ida_y_vuelta_aunque_envien_otra_empresa_habilitada(): void
    {
        DB::table('empresa')->where('id', 2)->update(['servicio_ida_vuelta' => true]);
        try {
            app(RecojoController::class)->store($this->shipmentRequest(true));
            $this->fail('Debe rechazar el servicio para la empresa autenticada.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('ida_vuelta', $exception->errors());
        }
    }

    public function test_empresa_habilitada_crea_dos_guias_y_deshabilitada_puede_enviar_sin_retorno(): void
    {
        foreach ([false, true] as $returnTrip) {
            DB::table('empresa')->where('id', 1)->update(['servicio_ida_vuelta' => $returnTrip]);
            $controller = Mockery::mock(RecojoController::class, [app(ContratoCodigoService::class)])
                ->makePartial()->shouldAllowMockingProtectedMethods();
            $controller->shouldReceive('createContratoDesdePayload')->times($returnTrip ? 2 : 1)
                ->andReturn((new Recojo)->forceFill(['id' => 1, 'codigo' => 'IDA']), (new Recojo)->forceFill(['id' => 2, 'codigo' => 'VUELTA']));
            $response = $controller->store($this->shipmentRequest($returnTrip));
            $this->assertSame(200, $response->getStatusCode());
            $this->assertCount($returnTrip ? 2 : 1, $response->getData(true)['codigos']);
        }
    }

    public function test_permisos_disponibles_para_roles(): void
    {
        $permissions = AclPermissionRegistry::allPermissionNames();
        $this->assertContains('servicios-adicionales.index', $permissions);
        $this->assertContains('feature.servicios-adicionales.edit', $permissions);
        $this->assertSame(['feature.servicios-adicionales.edit'], AclPermissionRegistry::authorizationPermissionsForRouteAccess('servicios-adicionales.update'));
    }
}
