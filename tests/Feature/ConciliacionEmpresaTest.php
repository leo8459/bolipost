<?php

namespace Tests\Feature;

use App\Http\Controllers\ConciliacionEmpresaController;
use App\Models\ConciliacionEmpresa;
use App\Models\Empresa;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ConciliacionEmpresaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente')->nullable();
            $table->timestamps();
        });
        Schema::create('conciliaciones_empresa', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->timestamp('gestora_at')->nullable();
            $table->unsignedBigInteger('gestora_por')->nullable();
            $table->timestamp('conciliacion_at')->nullable();
            $table->unsignedBigInteger('conciliacion_por')->nullable();
            $table->string('documento_path')->nullable();
            $table->string('documento_nombre')->nullable();
            $table->string('documento_mime')->nullable();
            $table->unsignedBigInteger('documento_tamano')->nullable();
            $table->timestamp('documento_at')->nullable();
            $table->unsignedBigInteger('documento_por')->nullable();
            $table->string('factura_venta_id')->nullable()->unique();
            $table->string('factura_detalle_id')->nullable();
            $table->text('factura_descripcion')->nullable();
            $table->string('factura_codigo_orden')->nullable();
            $table->string('factura_codigo_seguimiento')->nullable();
            $table->timestamp('factura_fecha')->nullable();
            $table->decimal('factura_monto', 15, 2)->nullable();
            $table->unsignedSmallInteger('facturado_anio')->nullable();
            $table->unsignedTinyInteger('facturado_mes')->nullable();
            $table->timestamp('por_cobrar_at')->nullable();
            $table->unsignedBigInteger('por_cobrar_por')->nullable();
            $table->string('factura_cuf')->nullable();
            $table->string('factura_numero')->nullable();
            $table->string('factura_pdf_path')->nullable();
            $table->string('factura_razon_social')->nullable();
            $table->string('factura_codigo_cliente')->nullable();
            $table->string('factura_numero_documento')->nullable();
            $table->string('factura_tipo_documento')->nullable();
            $table->timestamp('pago_recibido_at')->nullable();
            $table->unsignedBigInteger('pago_recibido_por')->nullable();
            $table->string('pago_comprobante_path')->nullable();
            $table->string('pago_comprobante_nombre')->nullable();
            $table->unsignedBigInteger('pago_comprobante_tamano')->nullable();
            $table->timestamp('confirmacion_pago_at')->nullable();
            $table->unsignedBigInteger('confirmacion_pago_por')->nullable();
            $table->timestamp('conciliado_at')->nullable();
            $table->unsignedBigInteger('conciliado_por')->nullable();
            $table->timestamps();
            $table->unique(['empresa_id', 'anio', 'mes']);
        });

        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'Empresa Uno', 'sigla' => 'E1', 'codigo_cliente' => '001'],
            ['id' => 2, 'nombre' => 'Empresa Dos', 'sigla' => 'E2', 'codigo_cliente' => '002'],
        ]);
    }

    public function test_muestra_los_doce_meses_y_todas_las_empresas(): void
    {
        ConciliacionEmpresa::query()->create([
            'empresa_id' => 1,
            'anio' => 2026,
            'mes' => 8,
            'gestora_at' => now(),
        ]);

        $view = app(ConciliacionEmpresaController::class)->index(
            Request::create('/conciliacion/conciliaciones', 'GET', ['anio' => 2026, 'mes' => 8])
        );

        $this->assertSame('conciliacion.conciliaciones', $view->getName());
        $this->assertCount(12, $view->getData()['resumenMeses']);
        $this->assertCount(2, $view->getData()['empresas']);
        $this->assertSame(0, $view->getData()['resumenMeses'][8]['documentos']);
    }

    public function test_usuario_con_rol_empresa_solo_ve_su_empresa_asignada(): void
    {
        Empresa::query()->whereKey(2)->update(['codigo_cliente' => '001']);
        ConciliacionEmpresa::query()->create([
            'empresa_id' => 1,
            'anio' => 2026,
            'mes' => 8,
            'conciliado_at' => now(),
        ]);
        ConciliacionEmpresa::query()->create([
            'empresa_id' => 2,
            'anio' => 2026,
            'mes' => 8,
            'conciliado_at' => now(),
        ]);

        $request = Request::create('/conciliacion/conciliaciones', 'GET', ['anio' => 2026, 'mes' => 8]);
        $request->setUserResolver(fn () => $this->usuarioEmpresa(1));
        $view = app(ConciliacionEmpresaController::class)->index($request);

        $this->assertSame([1], $view->getData()['empresas']->pluck('id')->all());
        $this->assertSame(1, $view->getData()['resumenMeses'][8]['total']);
        $this->assertSame(1, $view->getData()['resumenMeses'][8]['conciliados']);
    }

    public function test_usuario_empresa_no_puede_modificar_otra_empresa(): void
    {
        $request = $this->periodRequest();
        $request->setUserResolver(fn () => $this->usuarioEmpresa(1));

        try {
            app(ConciliacionEmpresaController::class)->subirDocumento(
                $request,
                Empresa::query()->findOrFail(2)
            );
            $this->fail('Se esperaba una respuesta 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    public function test_documento_no_puede_reemplazarse_despues_de_marcar_conciliado(): void
    {
        Storage::fake('public');
        $controller = app(ConciliacionEmpresaController::class);
        $empresa = Empresa::query()->findOrFail(1);

        $request = $this->periodRequest();
        $request->files->set('documento', UploadedFile::fake()->create(
            'conciliacion-agosto.xlsx',
            25,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ));
        $controller->subirDocumento($request, $empresa);

        $registro = ConciliacionEmpresa::query()->firstOrFail();
        $this->assertSame(1, $registro->empresa_id);
        $this->assertSame(2026, $registro->anio);
        $this->assertSame(8, $registro->mes);
        $this->assertNotNull($registro->conciliacion_at);
        $this->assertNull($registro->conciliado_at);
        $this->assertSame('conciliacion-agosto.xlsx', $registro->documento_nombre);
        Storage::disk('public')->assertExists($registro->documento_path);

        $controller->marcarConciliado(Request::create('/conciliado', 'POST'), $registro);
        $this->assertNotNull($registro->refresh()->conciliado_at);

        $pathAnterior = $registro->documento_path;
        $reemplazo = $this->periodRequest();
        $reemplazo->files->set('documento', UploadedFile::fake()->create(
            'conciliacion-corregida.xlsx',
            30,
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
        ));
        $controller->subirDocumento($reemplazo, $empresa);

        $registro->refresh();
        $this->assertSame('conciliacion-agosto.xlsx', $registro->documento_nombre);
        $this->assertNotNull($registro->conciliado_at);
        $this->assertSame($pathAnterior, $registro->documento_path);
        Storage::disk('public')->assertExists($pathAnterior);
    }

    public function test_asocia_una_factura_verificada_por_la_api_al_paso_por_cobrar(): void
    {
        Storage::fake('public');
        config([
            'services.facturacion_reports.base_url' => 'https://safe.example.test/api',
            'services.facturacion_reports.token' => 'test-token',
            'services.facturacion_bridge.sefe_public_base_url' => 'https://sefe.example.test',
        ]);
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'sefe.example.test')) {
                return Http::response('%PDF-1.7 test invoice', 200, ['Content-Type' => 'application/pdf']);
            }
            if (str_contains($request->url(), '/ventas/consultar/')) {
                return Http::response([
                    'cuf' => 'CUF-TEST-100',
                    'nroFactura' => 100,
                    'detalleFactura' => ['cabecera' => [
                        'nombreRazonSocial' => 'EMPRESA DE PRUEBA',
                        'codigoCliente' => 'CLI-100',
                        'numeroDocumento' => '1234567',
                        'codigoTipoDocumentoIdentidad' => 5,
                    ]],
                ]);
            }
            if (str_contains($request->url(), '/detalle')) {
                return Http::response(['servicio' => ['rows' => [[
                    'ventaId' => 'cart-100',
                    'detalleId' => null,
                    'descripcion' => 'Servicio Contratos - MES DE JULIO',
                    'codigoOrden' => 'VFC-100',
                    'codigoSeguimiento' => '123456',
                    'fecha' => '2026-08-21 10:00:00',
                    'totalLinea' => 1195,
                ]]]]);
            }

            return Http::response(['servicios' => [[
                'servicio' => 'Servicio Contratos por concepto de pago de servicios de courier correspondiente',
            ]]]);
        });
        ConciliacionEmpresa::query()->create([
            'empresa_id' => 1,
            'anio' => 2026,
            'mes' => 7,
            'documento_path' => 'conciliaciones/julio.pdf',
            'conciliado_at' => now(),
        ]);

        $disponibles = app(ConciliacionEmpresaController::class)->facturasDisponibles(
            Request::create('/facturas-disponibles', 'GET', ['anio' => 2026, 'mes' => 8])
        );
        $this->assertSame('cart-100', $disponibles->getData(true)['facturas'][0]['ventaId']);
        $this->assertSame('EMPRESA DE PRUEBA', $disponibles->getData(true)['facturas'][0]['razonSocial']);
        $this->assertSame('CLI-100', $disponibles->getData(true)['facturas'][0]['codigoCliente']);
        $this->assertSame('1234567', $disponibles->getData(true)['facturas'][0]['numeroDocumento']);

        app(ConciliacionEmpresaController::class)->asociarPorCobrar(Request::create('/por-cobrar', 'POST', [
            'empresa_id' => 1,
            'anio' => 2026,
            'mes' => 7,
            'facturado_anio' => 2026,
            'facturado_mes' => 8,
            'factura_venta_id' => 'cart-100',
        ]));

        $registro = ConciliacionEmpresa::query()->firstOrFail();
        $this->assertSame('cart-100', $registro->factura_venta_id);
        $this->assertSame('VFC-100', $registro->factura_codigo_orden);
        $this->assertSame('1195.00', $registro->factura_monto);
        $this->assertSame('CUF-TEST-100', $registro->factura_cuf);
        $this->assertSame('100', $registro->factura_numero);
        $this->assertSame('EMPRESA DE PRUEBA', $registro->factura_razon_social);
        $this->assertSame('CLI-100', $registro->factura_codigo_cliente);
        $this->assertSame('1234567', $registro->factura_numero_documento);
        Storage::disk('public')->assertExists($registro->factura_pdf_path);
        $this->assertNotNull($registro->por_cobrar_at);
    }

    public function test_registra_el_pago_recibido_despues_de_asociar_la_factura(): void
    {
        Storage::fake('public');
        $registro = ConciliacionEmpresa::query()->create([
            'empresa_id' => 1,
            'anio' => 2026,
            'mes' => 7,
            'documento_path' => 'conciliaciones/julio.pdf',
            'factura_venta_id' => 'cart-pagado-1',
            'factura_monto' => 500,
        ]);

        $request = Request::create('/pago-recibido', 'POST');
        $request->files->set('comprobante_pago', UploadedFile::fake()->create(
            'confirmacion-pago.pdf',
            20,
            'application/pdf'
        ));
        app(ConciliacionEmpresaController::class)->marcarPagoRecibido($request, $registro);

        $registro->refresh();
        $this->assertNotNull($registro->pago_recibido_at);
        $this->assertSame('confirmacion-pago.pdf', $registro->pago_comprobante_nombre);
        Storage::disk('public')->assertExists($registro->pago_comprobante_path);

        app(ConciliacionEmpresaController::class)->confirmarPago(
            Request::create('/confirmacion-pago', 'POST'),
            $registro
        );
        $this->assertNotNull($registro->refresh()->confirmacion_pago_at);
    }

    private function periodRequest(): Request
    {
        return Request::create('/conciliacion/conciliaciones', 'POST', [
            'anio' => 2026,
            'mes' => 8,
        ]);
    }

    private function usuarioEmpresa(int $empresaId): User
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['empresa_id' => $empresaId]);
        $user->shouldReceive('hasRole')->with('empresa')->andReturnTrue();

        return $user;
    }
}
