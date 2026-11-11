<?php

namespace Tests\Feature;

use App\Http\Controllers\PackageControlController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class PackageControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value')->nullable();
        });
        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->string('codigo_cliente');
        });
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->integer('empresa_id')->nullable();
        });

        foreach (['paquetes_contrato', 'paquetes_ems'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                foreach (['codigo', 'cod_especial', 'origen', 'contenido', 'observacion'] as $column) {
                    $table->string($column)->nullable();
                }
                $table->string('cantidad')->nullable();
                $table->decimal('peso', 10, 3)->nullable();
                $table->decimal('precio', 10, 2)->nullable();
                $table->timestamps();
                if ($name === 'paquetes_contrato') {
                    foreach (['codigo_madre', 'destino', 'nombre_r', 'telefono_r', 'nombre_d', 'telefono_d', 'direccion_d'] as $column) {
                        $table->string($column)->nullable();
                    }
                    $table->integer('estados_id')->nullable();
                    $table->integer('empresa_id')->nullable();
                    $table->integer('user_id')->nullable();
                    $table->timestamp('fecha_recojo')->nullable();
                } else {
                    foreach (['ciudad', 'nombre_remitente', 'telefono_remitente', 'nombre_destinatario', 'telefono_destinatario', 'direccion'] as $column) {
                        $table->string($column)->nullable();
                    }
                    $table->integer('estado_id')->nullable();
                }
            });
        }
        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => ' entregado '],
            ['id' => 2, 'nombre_estado' => 'TRANSITO'],
            ['id' => 3, 'nombre_estado' => 'DEVOLUCION'],
            ['id' => 4, 'nombre_estado' => 'NO ENTREGADO'],
        ]);
        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'Empresa Directa', 'codigo_cliente' => 'DIR-001'],
            ['id' => 2, 'nombre' => 'Empresa Antigua', 'codigo_cliente' => 'ANT-002'],
        ]);
        DB::table('users')->insert(['id' => 1, 'empresa_id' => 2]);
    }

    public function test_each_service_has_its_own_summary_and_renders_details(): void
    {
        foreach (['contratos', 'ems'] as $service) {
            $this->package($service, 'ENTREGADO-'.$service, 1, ['cantidad' => 3]);
            $this->package($service, 'TRANSITO-'.$service, 2);
            $this->package($service, 'DEVUELTO-'.$service, 3);
            $this->package($service, 'SIN-ESTADO-'.$service, null);
        }
        $this->package('ems', 'NO-ENTREGADO-EMS', 4);

        foreach (['contratos' => 4, 'ems' => 5] as $service => $total) {
            $view = $this->control($service);
            $summary = $view->getData()['summary'];
            $this->assertSame($total, $summary['total']);
            $this->assertSame($total + 2, $summary['packages']);
            $this->assertSame(1, $summary['delivered']);
            $this->assertSame($total - 1, $summary['not_delivered']);
            $this->assertSame(1, $summary['transit']);
            $this->assertSame(1, $summary['returned']);
            $html = $view->render();
            $this->assertStringContainsString('ENTREGADO-'.$service, $html);
            $this->assertStringContainsString('No entregados', $html);
            $this->assertStringContainsString('data-details=', $html);
            $this->assertStringContainsString('id="packageControlDetails"', $html);
            $other = $service === 'ems' ? 'contratos' : 'ems';
            $this->assertStringNotContainsString('ENTREGADO-'.$other, $html);
        }
    }

    public function test_regional_scope_applies_to_search_summary_and_both_route_ends(): void
    {
        foreach (['contratos', 'ems'] as $service) {
            $this->package($service, 'ORIGEN-LP', 1, ['origen' => ' la paz ', 'destination' => 'ORURO']);
            $this->package($service, 'DESTINO-LP', 2, ['origen' => 'ORURO', 'destination' => 'LA PAZ']);
            $this->package($service, 'FUERA', 1, ['origen' => 'ORURO', 'destination' => 'ORURO']);
            $data = $this->control($service, [], ['LA PAZ'])->getData();
            $this->assertSame(2, $data['summary']['total']);
            $this->assertSame(1, $data['summary']['delivered']);
            $this->assertSame(0, $this->control($service, ['q' => 'FUERA'], ['LA PAZ'])->getData()['summary']['total']);
            $this->assertSame(0, $this->control($service, [], [])->getData()['summary']['total']);
        }
    }

    public function test_delivery_and_date_filters_keep_unknown_and_failed_states_pending(): void
    {
        foreach (['contratos', 'ems'] as $service) {
            $this->package($service, 'DONE', 1);
            $this->package($service, 'FAILED', 4);
            $this->package($service, 'UNKNOWN', null);
            $this->package($service, 'OLD', 2, ['created_at' => '2026-10-01 10:00:00']);
            $filters = ['entrega' => 'no_entregados', 'fecha_desde' => '2026-11-11', 'fecha_hasta' => '2026-11-11'];
            $data = $this->control($service, $filters)->getData();
            $this->assertSame(2, $data['summary']['total']);
            $this->assertSame(0, $data['summary']['delivered']);
            $this->assertEqualsCanonicalizing(['FAILED', 'UNKNOWN'], $data['paquetes']->pluck('codigo')->all());
            $delivered = $this->control($service, ['entrega' => 'entregados'])->getData();
            $this->assertSame(['DONE'], $delivered['paquetes']->pluck('codigo')->all());
            $this->assertSame(0, $delivered['summary']['not_delivered']);
        }
    }

    public function test_contract_search_resolves_direct_and_legacy_companies_and_parent_codes(): void
    {
        $this->package('contratos', 'DIRECTO', 1, ['empresa_id' => 1, 'user_id' => 1, 'codigo_madre' => 'MADRE-123']);
        $this->package('contratos', 'ANTIGUO', 2, ['user_id' => 1]);
        $this->assertSame('DIRECTO', $this->control('contratos', ['q' => 'directa'])->getData()['paquetes']->first()->codigo);
        $this->assertSame('ANTIGUO', $this->control('contratos', ['q' => 'ant-002'])->getData()['paquetes']->first()->codigo);
        $this->assertSame(1, $this->control('contratos', ['q' => 'madre-123'])->getData()['summary']['total']);
    }

    public function test_summary_counts_all_pages_and_state_options_remain_available(): void
    {
        for ($index = 1; $index <= 35; $index++) {
            $this->package('ems', 'EMS-'.$index, $index <= 31 ? 1 : 2);
        }
        $data = $this->control('ems')->getData();
        $this->assertCount(30, $data['paquetes']);
        $this->assertSame(35, $data['summary']['total']);
        $this->assertSame(31, $data['summary']['delivered']);
        $selected = $this->control('ems', ['estado' => 'TRANSITO'])->getData();
        $this->assertSame(4, $selected['summary']['total']);
        $this->assertContains(' entregado ', $selected['estados']);
    }

    public function test_legacy_contract_quantities_allow_text_units_and_empty_values(): void
    {
        $this->package('contratos', 'CAJAS', 1, ['cantidad' => '10 cajas']);
        $this->package('contratos', 'SOBRES', 2, ['cantidad' => '12 SOBRES']);
        $this->package('contratos', 'VACIO', null, ['cantidad' => '']);
        $this->package('contratos', 'NULO', null, ['cantidad' => null]);
        $this->assertSame(22, $this->control('contratos')->getData()['summary']['packages']);
    }

    private function package(string $service, string $code, ?int $state, array $values = []): void
    {
        $destination = $values['destination'] ?? 'ORURO';
        unset($values['destination']);
        $isContract = $service === 'contratos';
        DB::table($isContract ? 'paquetes_contrato' : 'paquetes_ems')->insert(array_merge([
            'codigo' => $code,
            $isContract ? 'estados_id' : 'estado_id' => $state,
            $isContract ? 'destino' : 'ciudad' => $destination,
            $isContract ? 'nombre_r' : 'nombre_remitente' => 'Cliente de prueba',
            'origen' => 'LA PAZ',
            'cantidad' => 1,
            'created_at' => '2026-11-11 10:00:00',
        ], $values));
    }

    private function control(string $service, array $filters = [], ?array $departments = null): \Illuminate\View\View
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('getRoleNames')->andReturn(collect($departments === null ? ['administrador'] : ['operador']));
        $user->shouldReceive('hasGlobalDepartmentAccess')->andReturn($departments === null);
        $user->shouldReceive('regionalesLista')->andReturn($departments ?? []);
        $request = Request::create('/control-paquetes/'.$service, 'GET', $filters);
        $request->setUserResolver(fn () => $user);

        return app(PackageControlController::class)->{$service}($request);
    }
}
