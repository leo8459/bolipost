<?php

namespace Tests\Feature;

use App\Http\Controllers\RecojoController;
use App\Http\Controllers\TarifaContratoController;
use App\Livewire\PaquetesEms;
use App\Models\Recojo;
use App\Models\ServicioContrato;
use App\Models\TarifaContrato;
use App\Models\TarifarioContrato;
use App\Models\User;
use App\Services\ContratoCodigoService;
use App\Services\TarifarioContratoService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TarifaContratoBulkTest extends TestCase
{
    protected TarifarioContrato $tarifario;

    protected function setUp(): void
    {
        parent::setUp();
        $middleware = app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName('tarifa-contrato.store'));
        $this->withoutMiddleware(array_values(array_filter($middleware, fn ($name) => $name !== SubstituteBindings::class)));
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 1, 'name' => 'Administrador']);
        $user->shouldReceive('hasRole')->andReturnTrue();
        $this->actingAs($user);

        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo_cliente')->nullable();
            $table->string('sigla')->nullable();
        });
        Schema::create('tarifa_contrato', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresa');
            foreach (['origen', 'destino', 'servicio'] as $field) {
                $table->string($field);
            }
            foreach (['direccion', 'zona', 'provincia', 'provincia_origen'] as $field) {
                $table->string($field)->nullable();
            }
            $table->decimal('peso', 10, 2)->nullable();
            foreach (['kilo', 'kilo_extra', 'retencion'] as $field) {
                $table->decimal($field, 10, 2);
            }
            $table->unsignedInteger('horas_entrega');
            $table->timestamps();
        });
        DB::table('empresa')->insert([
            ['id' => 1, 'nombre' => 'Empresa uno', 'codigo_cliente' => '001', 'sigla' => 'UNO'],
            ['id' => 2, 'nombre' => 'Empresa dos', 'codigo_cliente' => '002', 'sigla' => 'DOS'],
        ]);
        (require database_path('migrations/2026_11_16_120000_create_tarifarios_contrato.php'))->up();
        (require database_path('migrations/2026_11_16_130000_create_servicios_contrato.php'))->up();
        foreach ([2026, 2027] as $year) {
            foreach (ServicioContrato::PREDEFINIDOS as $name) {
                ServicioContrato::asegurar($name, $year);
            }
        }
        $this->tarifario = TarifarioContrato::create(['nombre' => 'Tarifario A', 'anio' => 2026]);
    }

    private function row(array $overrides = []): array
    {
        return array_replace([
            'tarifario_contrato_id' => $this->tarifario->id, 'origen' => 'LA PAZ', 'destino' => 'COCHABAMBA',
            'servicio' => 'ENVIO NACIONAL (REGULAR)', 'kilo' => '20.50', 'kilo_extra' => '5',
            'retencion' => '0', 'horas_entrega' => '24',
        ], $overrides);
    }

    public function test_saves_multiple_routes_skips_blank_rows_and_normalizes_excel_values(): void
    {
        $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [
            $this->row(['origen' => 'chuquisaca', 'servicio' => 'envio local (regular)', 'direccion' => ' calle 1 ', 'kilo' => '1.250,50', 'peso' => '2,5']),
            array_fill_keys(['empresa_id', 'origen', 'destino', 'servicio', 'kilo', 'kilo_extra', 'retencion', 'horas_entrega'], ''),
            $this->row(['destino' => 'beni']),
        ]])->assertSessionHasNoErrors()->assertRedirect(route('tarifa-contrato.index'));

        $this->assertDatabaseCount('tarifa_contrato', 2);
        $this->assertDatabaseHas('tarifa_contrato', [
            'tarifario_contrato_id' => $this->tarifario->id, 'empresa_id' => null, 'origen' => 'SUCRE', 'servicio' => 'ENVIO LOCAL(REGULAR)',
            'direccion' => 'CALLE 1', 'kilo' => 1250.50, 'peso' => 2.5,
        ]);
        $this->assertDatabaseHas('tarifa_contrato', ['tarifario_contrato_id' => $this->tarifario->id, 'destino' => 'TRINIDAD']);
    }

    public function test_invalid_row_prevents_all_writes_and_preserves_submitted_rows(): void
    {
        $rows = [$this->row(), $this->row(['kilo' => '-1', 'retencion' => 101, 'horas_entrega' => '1.5'])];
        $this->from(route('tarifa-contrato.create'))->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => $rows])
            ->assertRedirect(route('tarifa-contrato.create'))
            ->assertSessionHasErrors(['tarifas.1.kilo', 'tarifas.1.retencion', 'tarifas.1.horas_entrega'])
            ->assertSessionHasInput('tarifas', $rows);
        $this->assertDatabaseCount('tarifa_contrato', 0);
    }

    public function test_rejects_empty_sheet_oversized_sheet_and_malformed_rows(): void
    {
        foreach ([['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [[]]], ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => array_fill(0, 51, $this->row())], ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => 'invalid'], ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => ['invalid']]] as $payload) {
            $this->post(route('tarifa-contrato.store'), $payload)->assertSessionHasErrors();
            $this->assertDatabaseCount('tarifa_contrato', 0);
        }
    }

    public function test_invalid_numbers_and_nested_values_are_validated(): void
    {
        $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [
            $this->row(['peso' => 'texto', 'origen' => ['LA PAZ']]),
        ]])->assertSessionHasErrors(['tarifas.0.peso', 'tarifas.0.origen']);
        $this->assertDatabaseCount('tarifa_contrato', 0);
    }

    public function test_weight_is_optional_when_missing_blank_or_null(): void
    {
        $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [
            $this->row(), $this->row(['peso' => '']), $this->row(['peso' => null]),
        ]])->assertSessionHasNoErrors()->assertRedirect(route('tarifa-contrato.index'));
        $this->assertDatabaseCount('tarifa_contrato', 3);
        $this->assertSame(3, TarifaContrato::whereNull('peso')->count());
    }

    public function test_optional_retention_defaults_to_zero_for_blank_and_missing_values(): void
    {
        $withoutRetention = $this->row();
        unset($withoutRetention['retencion']);
        $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [
            $this->row(['retencion' => '']), $this->row(['retencion' => null]), $withoutRetention,
        ]])->assertSessionHasNoErrors()->assertRedirect(route('tarifa-contrato.index'));
        $this->assertDatabaseCount('tarifa_contrato', 3);
        $this->assertSame(3, TarifaContrato::query()->where('retencion', 0)->count());
    }

    public function test_legacy_excel_import_still_accepts_an_optional_weight_column(): void
    {
        $existing = TarifaContrato::create($this->row(['destino' => 'ORURO', 'peso' => 1.5, 'kilo' => 99]));
        $path = tempnam(sys_get_temp_dir(), 'tarifa-test-');
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['origen', 'destino', 'servicio', 'kilo_de_1_a_2', 'kilo', 'kilo_extra', 'horas_entrega'],
            ['LA PAZ', 'ORURO', 'ENVIO NACIONAL (REGULAR)', '1.5', '20', '5', '24'],
        ]);
        try {
            (new Xlsx($spreadsheet))->save($path);
            $this->post(route('tarifa-contrato.import'), [
                'tarifario_contrato_id' => $this->tarifario->id,
                'archivo' => new UploadedFile($path, 'tarifas.xlsx', null, null, true),
            ])->assertSessionHasNoErrors()->assertSessionMissing('import_errors')
                ->assertRedirect(route('tarifa-contrato.import-form', ['tarifario_id' => $this->tarifario->id]));
            $this->assertDatabaseHas('tarifa_contrato', ['peso' => 1.5, 'retencion' => 0, 'kilo' => 20]);
            $this->assertDatabaseCount('tarifa_contrato', 1);
            $this->assertSame('20.00', $existing->fresh()->kilo);
        } finally {
            unlink($path);
            $spreadsheet->disconnectWorksheets();
        }
    }

    public function test_excel_template_matches_platform_titles_and_can_be_imported_with_those_titles(): void
    {
        $existing = TarifaContrato::create($this->row(['destino' => 'ORURO', 'peso' => 1.5, 'kilo' => 99, 'provincia_origen' => 'MURILLO', 'provincia' => 'CERCADO']));
        $response = $this->get(route('tarifa-contrato.template-excel'))->assertOk();
        $path = tempnam(sys_get_temp_dir(), 'tarifa-labels-');
        $book = null;
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::load($path);
            $sheet = $book->getSheetByName('TarifaContrato');
            preg_match_all("/label: '([^']+)'/u", file_get_contents(public_path('js/tarifa-contrato-sheet.js')), $titles);
            $this->assertCount(11, $titles[1]);
            $this->assertSame('K', $sheet->getHighestColumn());
            $this->assertSame($titles[1], $sheet->rangeToArray('A1:K1')[0]);
            $this->assertTrue($sheet->getStyle('A1:K1')->getAlignment()->getWrapText());
            $this->assertSame('0', $sheet->getStyle('F2')->getNumberFormat()->getFormatCode());

            $sheet->fromArray([
                'LA PAZ', 'ORURO', 'ENVIO NACIONAL (REGULAR)', 25.5, 5, 24,
                'Calle 1', 'CENTRO', 'MURILLO', 'CERCADO', 0,
            ], null, 'A2');
            $sheet->fromArray([
                'LA PAZ', 'SANTA CRUZ', 'ENVIO NACIONAL (REGULAR)', 30, 6, 48,
                'Calle 2', 'SUR', 'MURILLO', 'ANDRES IBANEZ', 0,
            ], null, 'A3');
            (new Xlsx($book))->save($path);
            $this->post(route('tarifa-contrato.import'), [
                'tarifario_contrato_id' => $this->tarifario->id,
                'archivo' => new UploadedFile($path, 'tarifas.xlsx', null, null, true),
            ])->assertSessionHasNoErrors()->assertSessionMissing('import_errors');
            $this->assertDatabaseHas('tarifa_contrato', [
                'id' => $existing->id,
                'tarifario_contrato_id' => $this->tarifario->id,
                'peso' => 1.5, 'kilo' => 25.5, 'kilo_extra' => 5, 'horas_entrega' => 24,
                'direccion' => 'CALLE 1', 'zona' => 'CENTRO', 'provincia_origen' => 'MURILLO', 'provincia' => 'CERCADO',
            ]);
            $this->assertDatabaseCount('tarifa_contrato', 2);
            $this->assertDatabaseHas('tarifa_contrato', ['destino' => 'SANTA CRUZ', 'kilo' => 30, 'kilo_extra' => 6, 'horas_entrega' => 48, 'peso' => null]);
        } finally {
            $book?->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_database_failure_rolls_back_the_whole_batch(): void
    {
        $this->withoutExceptionHandling();
        // Throw on the second insert to prove that the first insert is rolled back.
        $dispatcher = TarifaContrato::getEventDispatcher();
        TarifaContrato::setEventDispatcher(clone $dispatcher);
        TarifaContrato::creating(function (TarifaContrato $tarifa) {
            if ($tarifa->destino === 'TRINIDAD') {
                throw new RuntimeException('Simulated insert failure');
            }
        });
        try {
            $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [$this->row(), $this->row(['destino' => 'TRINIDAD'])]]);
            $this->fail('Expected insert failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated insert failure', $exception->getMessage());
            $this->assertDatabaseCount('tarifa_contrato', 0);
        } finally {
            TarifaContrato::setEventDispatcher($dispatcher);
        }
    }

    public function test_legacy_single_tariff_creation_still_works(): void
    {
        $this->post(route('tarifa-contrato.store'), $this->row(['action' => 'save_and_new']))
            ->assertSessionHasNoErrors()->assertRedirect(route('tarifa-contrato.create'));
        $this->assertDatabaseCount('tarifa_contrato', 1);
    }

    public function test_batch_keeps_the_existing_save_authorization(): void
    {
        auth()->forgetGuards();
        $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [$this->row()]])->assertForbidden();
        $this->assertDatabaseCount('tarifa_contrato', 0);
    }

    public function test_spreadsheet_renders_with_safely_encoded_initial_data(): void
    {
        $request = request();
        $request->setLaravelSession(app('session.store'));
        $view = app(TarifaContratoController::class)->create($request);
        $data = $view->getData();
        $data['defaults'] = $this->row(['direccion' => '</script><script>alert(1)</script>']);
        $data['errors'] = new ViewErrorBag;
        $html = Blade::render("@include('tarifa_contrato.spreadsheet') @stack('css') @stack('js')", $data);
        $this->assertStringContainsString('sheet-viewport', $html);
        $this->assertStringContainsString('tarifa-contrato-sheet.js', $html);
        $this->assertStringNotContainsString('</script><script>alert', $html);
        preg_match('/<script id="tarifas-sheet-config" type="application\/json">(.*?)<\/script>/s', $html, $matches);
        $config = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($data['defaults']['direccion'], $config['rows'][0]['direccion']);
        $this->assertStringContainsString('Tarifario A', $html);
    }

    public function test_creates_an_independent_copy_for_the_next_year(): void
    {
        $original = TarifaContrato::create($this->row());
        $this->post(route('tarifa-contrato.tarifarios-store'), [
            'nombre' => 'Tarifario A', 'anio' => 2027, 'copiar_de_id' => $this->tarifario->id,
        ])->assertSessionHasNoErrors();
        $nuevo = TarifarioContrato::where('anio', 2027)->firstOrFail();
        $copy = $nuevo->tarifas()->firstOrFail();
        $this->assertNotSame($original->id, $copy->id);
        $this->assertNull($nuevo->publicado_en);
        $this->put(route('tarifa-contrato.update', $copy), $this->row(['tarifario_contrato_id' => $nuevo->id, 'kilo' => 50]))->assertSessionHasNoErrors();
        $this->assertSame('20.50', $original->fresh()->kilo);
        $this->assertSame('50.00', $copy->fresh()->kilo);
    }

    public function test_assignment_switches_selected_companies_and_preserves_other_assignments_and_history(): void
    {
        $old = TarifaContrato::create($this->row());
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1, 2]);
        $nuevo = TarifarioContrato::create(['nombre' => 'Tarifario A', 'anio' => 2027]);
        $newRate = TarifaContrato::create($this->row(['tarifario_contrato_id' => $nuevo->id, 'kilo' => 40]));
        $this->post(route('tarifa-contrato.tarifarios-asignar', $nuevo), ['empresas' => [1]])->assertSessionHasNoErrors();
        $this->assertSame([$newRate->id], TarifaContrato::paraEmpresa(1)->pluck('id')->all());
        $this->assertSame([$old->id], TarifaContrato::paraEmpresa(2)->pluck('id')->all());
        $this->assertSame(2, TarifaContrato::paraEmpresa(1, false)->count());
        $this->assertNotNull($nuevo->fresh()->publicado_en);
        $this->assertDatabaseHas('tarifario_contrato_empresa', ['empresa_id' => 1, 'tarifario_contrato_id' => $this->tarifario->id, 'activo' => false]);
        $this->assertDatabaseHas('tarifario_contrato_empresa', ['empresa_id' => 2, 'tarifario_contrato_id' => $this->tarifario->id, 'activo' => true]);
    }

    public function test_cannot_assign_an_empty_schedule_or_nonexistent_company(): void
    {
        $this->post(route('tarifa-contrato.tarifarios-asignar', $this->tarifario), ['empresas' => [1]])->assertSessionHasErrors('empresas');
        $this->assertDatabaseCount('tarifario_contrato_empresa', 0);
        $this->post(route('tarifa-contrato.tarifarios-asignar', $this->tarifario), ['empresas' => [999]])->assertSessionHasErrors('empresas.0');
    }

    public function test_published_rates_cannot_be_edited_deleted_or_extended(): void
    {
        $tarifa = TarifaContrato::create($this->row());
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);
        $this->put(route('tarifa-contrato.update', $tarifa), $this->row(['kilo' => 99]))->assertSessionHasErrors('tarifario_contrato_id');
        $this->delete(route('tarifa-contrato.destroy', $tarifa))->assertSessionHasErrors('tarifario_contrato_id');
        $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [$this->row()]])->assertSessionHasErrors('tarifario_contrato_id');
        $this->assertDatabaseCount('tarifa_contrato', 1);
        $this->assertSame('20.50', $tarifa->fresh()->kilo);
    }

    public function test_new_shipments_use_current_schedule_and_existing_shipments_keep_the_original_price(): void
    {
        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('empresa_id');
            $table->unsignedBigInteger('tarifa_contrato_id');
            $table->decimal('peso', 10, 3);
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamps();
        });
        $old = TarifaContrato::create($this->row());
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);
        $shipment = Recojo::create(['empresa_id' => 1, 'tarifa_contrato_id' => $old->id, 'peso' => 2]);
        $nuevo = TarifarioContrato::create(['nombre' => 'Tarifario A', 'anio' => 2027]);
        $newRate = TarifaContrato::create($this->row(['tarifario_contrato_id' => $nuevo->id, 'kilo' => 40]));
        app(TarifarioContratoService::class)->asignar($nuevo->id, [1]);
        $controller = new class(app(ContratoCodigoService::class)) extends RecojoController
        {
            public function currentRate(): ?TarifaContrato
            {
                return $this->resolveTarifaContrato(1, 'LA PAZ', 'COCHABAMBA', 'ENVIO NACIONAL (REGULAR)', null);
            }
        };
        $this->assertSame($newRate->id, $controller->currentRate()?->id);
        $component = new class extends PaquetesEms
        {
            public function price(Recojo $shipment): bool
            {
                return $this->applyTarifaContratoPricing($shipment);
            }
        };
        $this->assertTrue($component->price($shipment));
        $this->assertSame($old->id, $shipment->fresh()->tarifa_contrato_id);
        $this->assertSame('25.50', $shipment->fresh()->precio);
        $this->assertSame(0, TarifaContrato::paraEmpresa(2)->count());
    }

    public function test_migration_preserves_existing_tariff_ids_and_company_access_without_guessing_the_year(): void
    {
        $legacy = TarifaContrato::create($this->row(['empresa_id' => 1]));
        $migration = require database_path('migrations/2026_11_16_120000_create_tarifarios_contrato.php');
        $services = require database_path('migrations/2026_11_16_130000_create_servicios_contrato.php');
        $services->down();
        $migration->down();
        $migration->up();
        $services->up();
        $preserved = TarifaContrato::paraEmpresa(1)->firstOrFail();
        $this->assertSame($legacy->id, $preserved->id);
        $this->assertSame('20.50', $preserved->kilo);
        $this->assertNull($preserved->tarifario->anio);
        $this->assertNotNull($preserved->tarifario->publicado_en);
    }

    public function test_requires_a_year_and_rejects_duplicate_name_and_year(): void
    {
        $this->post(route('tarifa-contrato.tarifarios-store'), ['nombre' => 'Tarifario A'])->assertSessionHasErrors('anio');
        $this->post(route('tarifa-contrato.tarifarios-store'), ['nombre' => 'Tarifario A', 'anio' => 2026])->assertSessionHasErrors('nombre');
        $this->assertDatabaseCount('tarifarios_contrato', 1);
    }

    public function test_administration_views_render_the_year_and_current_assignments(): void
    {
        TarifaContrato::create($this->row());
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);
        foreach (['tarifarios', 'index', 'importForm', 'servicios'] as $method) {
            $controller = app(TarifaContratoController::class);
            $view = in_array($method, ['index', 'servicios'], true) ? $controller->$method(request()) : $controller->$method();
            $template = file_get_contents($view->getPath());
            $template = preg_replace('/@extends\([^\n]+\)/', '', $template);
            $template = str_replace("@include('footer')", '', $template);
            $html = Blade::render($template." @yield('content') @stack('js')", $view->getData() + ['errors' => new ViewErrorBag]);
            $this->assertStringContainsString('Tarifario', $html);
            if ($method !== 'importForm') {
                $this->assertStringContainsString('2026', $html);
                if ($method !== 'servicios') {
                    $this->assertStringContainsString('Empresa uno', $html);
                }
            }
        }
    }

    public function test_creation_and_assignment_require_save_permission(): void
    {
        $registrar = Mockery::mock(PermissionRegistrar::class)->makePartial();
        $registrar->shouldReceive('getPermissions')->andReturn(new Collection);
        $this->app->instance(PermissionRegistrar::class, $registrar);
        config(['acl.route_permission.allow_when_permission_missing' => false]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id' => 2]);
        $user->shouldReceive('hasRole')->andReturnFalse();
        $user->shouldReceive('can')->andReturnFalse();
        $this->actingAs($user);
        $this->post(route('tarifa-contrato.tarifarios-store'), ['nombre' => 'Sin permiso', 'anio' => 2027])->assertForbidden();
        $this->post(route('tarifa-contrato.tarifarios-asignar', $this->tarifario), ['empresas' => [1]])->assertForbidden();
        $this->post(route('tarifa-contrato.servicios-store'), ['nombre' => 'SIN PERMISO', 'anio' => 2026])->assertForbidden();
        $this->assertDatabaseCount('tarifarios_contrato', 1);
        $this->assertDatabaseCount('tarifario_contrato_empresa', 0);
    }

    public function test_services_can_be_added_searched_and_filtered_by_year(): void
    {
        $this->post(route('tarifa-contrato.servicios-store'), ['nombre' => 'INVALIDO', 'anio' => 'incorrecto'])->assertSessionHasErrors('anio');
        session()->forget('errors');
        $this->post(route('tarifa-contrato.servicios-store'), ['nombre' => '  servicio   especial ', 'anio' => 2026])->assertSessionHasNoErrors();
        $this->post(route('tarifa-contrato.servicios-store'), ['nombre' => 'servicio especial', 'anio' => 2026])->assertSessionHasErrors('nombre');
        session()->forget('errors');
        $this->post(route('tarifa-contrato.servicios-store'), ['nombre' => 'servicio especial', 'anio' => 2027])->assertSessionHasNoErrors();
        $this->assertSame(2, ServicioContrato::where('nombre', 'SERVICIO ESPECIAL')->count());
        $view = app(TarifaContratoController::class)->servicios(Request::create('/', 'GET', ['anio' => 2027, 'q' => 'especial']));
        $services = $view->getData()['servicios'];
        $this->assertCount(1, $services);
        $this->assertSame(2027, $services->first()->anio);
        ServicioContrato::asegurar('EXCLUSIVO 2026', 2026);
        $names = $this->getJson(route('tarifa-contrato.servicios', ['anio' => 2027]))->assertOk()->assertJsonPath('anio', 2027)->json('servicios');
        $this->assertContains('SERVICIO ESPECIAL', $names);
        $this->assertNotContains('EXCLUSIVO 2026', $names);
    }

    public function test_service_selection_is_validated_against_the_tariff_year_and_saves_the_relationship(): void
    {
        $current = ServicioContrato::asegurar('ESPECIAL 2026', 2026);
        ServicioContrato::asegurar('SOLO 2027', 2027);
        $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [$this->row(['servicio' => 'especial 2026'])]])->assertSessionHasNoErrors();
        $tarifa = TarifaContrato::firstOrFail();
        $this->assertSame($current->id, $tarifa->servicio_contrato_id);
        $this->assertSame('ESPECIAL 2026', $tarifa->servicioCatalogo->nombre);
        $this->post(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [$this->row(), $this->row(['servicio' => 'SOLO 2027'])]])->assertSessionHasErrors('tarifas.1.servicio');
        $this->assertDatabaseCount('tarifa_contrato', 1);
    }

    public function test_unused_services_can_be_edited_and_deleted_but_used_services_are_protected(): void
    {
        $unused = ServicioContrato::asegurar('SIN USO', 2026);
        $this->put(route('tarifa-contrato.servicios-update', $unused), ['nombre' => 'nuevo nombre', 'anio' => 2027])->assertSessionHasNoErrors();
        $this->assertSame('NUEVO NOMBRE', $unused->fresh()->nombre);
        $this->delete(route('tarifa-contrato.servicios-destroy', $unused))->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('servicios_contrato', ['id' => $unused->id]);
        $tarifa = TarifaContrato::create($this->row());
        $service = $tarifa->servicioCatalogo;
        $this->put(route('tarifa-contrato.servicios-update', $service), ['nombre' => 'CAMBIAR USADO', 'anio' => 2026])->assertSessionHasErrors('nombre');
        $this->delete(route('tarifa-contrato.servicios-destroy', $service))->assertSessionHasErrors('nombre');
        $this->assertSame('ENVIO NACIONAL (REGULAR)', $service->fresh()->nombre);
        $this->assertSame($service->id, $tarifa->fresh()->servicio_contrato_id);
    }

    public function test_services_can_be_copied_to_another_year_without_duplicate_names(): void
    {
        ServicioContrato::asegurar('SERVICIO PERSONALIZADO', 2026);
        $originalCount = ServicioContrato::deAnio(2026)->count();
        foreach ([1, 2] as $attempt) {
            $this->post(route('tarifa-contrato.servicios-copy'), ['copiar_de_anio' => 2026, 'anio' => 2028])->assertSessionHasNoErrors();
            $this->assertSame($originalCount, ServicioContrato::deAnio(2028)->count());
        }
        $this->assertSame($originalCount, ServicioContrato::deAnio(2026)->count());
    }

    public function test_renewing_a_tariff_copies_custom_services_and_rebinds_their_year(): void
    {
        ServicioContrato::asegurar('SERVICIO PERSONALIZADO', 2026);
        $original = TarifaContrato::create($this->row(['servicio' => 'SERVICIO PERSONALIZADO']));
        $this->post(route('tarifa-contrato.tarifarios-store'), ['nombre' => 'Renovado', 'anio' => 2027, 'copiar_de_id' => $this->tarifario->id])->assertSessionHasNoErrors();
        $copy = TarifarioContrato::where('nombre', 'Renovado')->firstOrFail()->tarifas()->firstOrFail();
        $this->assertSame(2027, $copy->servicioCatalogo->anio);
        $this->assertSame(2026, $original->fresh()->servicioCatalogo->anio);
        $this->assertSame($original->kilo, $copy->kilo);
        $this->assertNotSame($original->servicio_contrato_id, $copy->servicio_contrato_id);
    }

    public function test_defining_the_year_of_an_existing_tariff_rebinds_services_without_changing_prices(): void
    {
        $tarifa = TarifaContrato::create($this->row());
        $this->put(route('tarifa-contrato.tarifarios-update', $this->tarifario), ['nombre' => 'Tarifario A', 'anio' => 2028])->assertSessionHasNoErrors();
        $this->assertSame(2028, $tarifa->fresh()->servicioCatalogo->anio);
        $this->assertSame('20.50', $tarifa->fresh()->kilo);
        $this->assertSame('ENVIO NACIONAL (REGULAR)', $tarifa->fresh()->servicio);
    }

    public function test_excel_catalog_contains_only_the_requested_year_services(): void
    {
        ServicioContrato::asegurar('SOLO 2026', 2026);
        ServicioContrato::asegurar('SOLO 2027', 2027);
        $path = tempnam(sys_get_temp_dir(), 'tarifa-year-');
        try {
            $response = $this->get(route('tarifa-contrato.template-excel', ['anio' => 2027]))->assertOk();
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::load($path);
            $names = array_column($book->getSheetByName('Catalogos')->toArray(), 0);
            $this->assertContains('SOLO 2027', $names);
            $this->assertNotContains('SOLO 2026', $names);
            $book->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_an_empty_new_year_does_not_automatically_copy_or_seed_services(): void
    {
        $this->post(route('tarifa-contrato.tarifarios-store'), ['nombre' => 'NUEVO AÑO', 'anio' => 2029])->assertSessionHasNoErrors();
        $this->assertSame(0, ServicioContrato::deAnio(2029)->count());
        ServicioContrato::asegurar('SERVICIO DEFINIDO PARA 2029', 2029);
        $names = $this->getJson(route('tarifa-contrato.servicios', ['anio' => 2029]))->assertOk()->json('servicios');
        $this->assertSame(['SERVICIO DEFINIDO PARA 2029'], $names);

        TarifaContrato::create($this->row());
        $this->post(route('tarifa-contrato.tarifarios-store'), ['nombre' => 'COPIA EXPLICITA', 'anio' => 2030, 'copiar_de_id' => $this->tarifario->id])->assertSessionHasNoErrors();
        $this->assertSame(['ENVIO NACIONAL (REGULAR)'], ServicioContrato::deAnio(2030)->pluck('nombre')->all());
    }

    public function test_custom_services_are_included_in_the_detail_pdf(): void
    {
        ServicioContrato::asegurar('SERVICIO PERSONALIZADO', 2026);
        TarifaContrato::create($this->row(['servicio' => 'SERVICIO PERSONALIZADO']));
        $response = app(TarifaContratoController::class)->reporteDetalle(request());
        $text = (new Parser)->parseContent($response->getContent())->getText();
        $this->assertStringContainsString('SERVICIO PERSONALIZADO', $text);
    }
}
