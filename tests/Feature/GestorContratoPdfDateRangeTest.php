<?php

namespace Tests\Feature;

use App\Http\Controllers\RecojoController;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class GestorContratoPdfDateRangeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'empresa_id' => 1, 'name' => 'Gestor']);
        $user->shouldReceive('can')->with('feature.paquetes-contrato.gestor.report')->andReturn(true);
        $this->actingAs($user);
    }

    public function test_requires_both_dates_and_rejects_reversed_range(): void
    {
        foreach ([[], ['fecha_desde' => '2026-09-18', 'fecha_hasta' => '2026-09-17']] as $dates) {
            $response = app(RecojoController::class)->gestorPdf(Request::create('/', 'GET', $dates));
            $this->assertSame(302, $response->getStatusCode());
            $this->assertTrue(session('errors')->has('fecha_hasta'));
        }
    }

    public function test_filters_by_pickup_date_including_the_entire_last_day(): void
    {
        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('sigla')->nullable();
            $table->string('codigo_cliente')->nullable();
        });
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->softDeletes();
        });
        Schema::create('cartero', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_paquetes_contrato');
            $table->string('imagen')->nullable();
            $table->text('imagen_devolucion')->nullable();
            $table->timestamps();
        });
        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('estados_id');
            $table->string('codigo');
            $table->string('imagen')->nullable();
            $table->decimal('peso')->default(1);
            $table->dateTime('fecha_recojo')->nullable();
            $table->timestamps();
        });
        DB::table('empresa')->insert(['id' => 1, 'nombre' => 'Empresa']);
        DB::table('estados')->insert(['id' => 1, 'nombre_estado' => 'ENTREGADO']);
        foreach (['2026-09-16 23:59:59', '2026-09-17 00:00:00', '2026-09-18 23:59:59', '2026-09-19 00:00:00', null] as $i => $date) {
            DB::table('paquetes_contrato')->insert([
                'empresa_id' => 1, 'estados_id' => 1, 'codigo' => 'CONT-'.$i,
                'fecha_recojo' => $date, 'created_at' => '2026-01-01 12:00:00',
            ]);
        }
        DB::table('estados')->insert([
            ['id' => 2, 'nombre_estado' => ' DEVOLUCION '],
            ['id' => 3, 'nombre_estado' => 'ACTIVO'],
        ]);
        $fotoEntrega = 'data:image/png;base64,'.base64_encode('foto-entrega');
        $fotoDevolucion = 'data:image/png;base64,'.base64_encode('foto-devolucion');
        foreach ([6 => 2, 7 => 3, 8 => 2] as $id => $estadoId) {
            DB::table('paquetes_contrato')->insert([
                'id' => $id, 'empresa_id' => 1, 'estados_id' => $estadoId,
                'codigo' => 'CONT-'.$id, 'fecha_recojo' => '2026-09-18 12:00:00',
                'imagen' => $fotoEntrega,
            ]);
        }
        DB::table('cartero')->insert([
            ['id_paquetes_contrato' => 6, 'imagen' => $fotoEntrega, 'imagen_devolucion' => $fotoDevolucion, 'updated_at' => '2026-09-18 12:00:00'],
            ['id_paquetes_contrato' => 6, 'imagen' => $fotoEntrega, 'imagen_devolucion' => '', 'updated_at' => '2026-09-18 13:00:00'],
            ['id_paquetes_contrato' => 3, 'imagen' => $fotoEntrega, 'imagen_devolucion' => $fotoDevolucion, 'updated_at' => '2026-09-18 12:00:00'],
        ]);
        $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->with('a4', 'landscape')->andReturnSelf();
        $downloadUrls = [];
        Pdf::shouldReceive('loadView')->once()->withArgs(function ($view, $data) use ($fotoEntrega, $fotoDevolucion, &$downloadUrls) {
            $this->assertSame('paquetes_contrato.gestor-pdf', $view);
            $this->assertSame(['CONT-8', 'CONT-6', 'CONT-2', 'CONT-1'], $data['contratos']->pluck('codigo')->all());
            $rows = $data['contratos']->keyBy('id');
            $this->assertSame($fotoDevolucion, $rows[6]->imagen_pdf);
            $this->assertSame($fotoEntrega, $rows[3]->imagen_pdf);
            $this->assertNull($rows[8]->imagen_pdf);
            $this->assertNull($rows[8]->imagen_descarga_url);
            $this->assertStringContainsString('/devolucion/descargar', $rows[6]->imagen_descarga_url);
            $this->assertStringContainsString('/entrega/descargar', $rows[3]->imagen_descarga_url);
            $this->assertSame('entregados-devolucion', $data['estadoFiltro']);
            $downloadUrls = [$rows[6]->imagen_descarga_url, $rows[3]->imagen_descarga_url];
            $this->assertSame('17/09/2026', $data['fechaDesde']->format('d/m/Y'));
            $this->assertSame('18/09/2026', $data['fechaHasta']->format('d/m/Y'));
            return true;
        })->andReturn($pdf);

        $response = app(RecojoController::class)->gestorPdf(Request::create('/', 'GET', [
            'estado' => 'entregados', 'fecha_desde' => '2026-09-17', 'fecha_hasta' => '2026-09-18',
        ]));
        $this->assertSame(200, $response->getStatusCode());
        foreach ($downloadUrls as $url) {
            $this->assertTrue(\Illuminate\Support\Facades\URL::hasValidSignature(Request::create($url), false));
        }
        $images = app(\App\Http\Controllers\DeliveryImageController::class);
        $this->assertSame('foto-devolucion', $images->downloadPackage('contrato', 6, 'devolucion')->getContent());
        $this->assertSame('foto-entrega', $images->downloadPackage('contrato', 3, 'entrega')->getContent());
    }
}
