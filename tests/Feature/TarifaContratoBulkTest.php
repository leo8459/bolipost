<?php

namespace Tests\Feature;

use App\Http\Controllers\RecojoController;
use App\Http\Controllers\TarifaContratoController;
use App\Http\Middleware\EnsureRoutePermission;
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
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use RuntimeException;
use Smalot\PdfParser\Parser;
use Spatie\Permission\Models\Permission;
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
        (require database_path('migrations/2026_11_19_120000_add_empresa_titular_to_tarifarios_contrato.php'))->up();
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
            $this->row(['origen' => 'sucre', 'servicio' => 'envio local (regular)', 'direccion' => ' calle 1 ', 'kilo' => '1.250,50', 'peso' => '2,5']),
            array_fill_keys(['empresa_id', 'origen', 'destino', 'servicio', 'kilo', 'kilo_extra', 'retencion', 'horas_entrega'], ''),
            $this->row(['destino' => 'trinidad']),
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

    public function test_routes_outside_the_department_catalog_are_rejected_for_single_and_multiple_rates(): void
    {
        $invalid = $this->row(['origen' => 'PANDO', 'destino' => 'BENI']);

        $this->postJson(route('tarifa-contrato.store'), $invalid)
            ->assertUnprocessable()->assertJsonValidationErrors(['origen', 'destino']);
        $this->postJson(route('tarifa-contrato.store'), ['tarifario_contrato_id' => $this->tarifario->id, 'tarifas' => [$invalid]])
            ->assertUnprocessable()->assertJsonValidationErrors(['tarifas.0.origen', 'tarifas.0.destino']);
        $this->postJson(route('tarifa-contrato.store'), $this->row(['origen' => ['LA PAZ']]))
            ->assertUnprocessable()->assertJsonValidationErrors('origen');
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
            $this->assertSame(['TarifaContrato', 'Catalogos', 'Instrucciones'], $book->getSheetNames());
            $sheet = $book->getSheetByName('TarifaContrato');
            preg_match_all("/label: '([^']+)'/u", file_get_contents(public_path('js/tarifa-contrato-sheet.js')), $titles);
            $this->assertCount(11, $titles[1]);
            $this->assertSame('K', $sheet->getHighestColumn());
            $this->assertSame($titles[1], $sheet->rangeToArray('A1:K1')[0]);
            $this->assertTrue($sheet->getStyle('A1:K1')->getAlignment()->getWrapText());
            $this->assertSame('0', $sheet->getStyle('F2')->getNumberFormat()->getFormatCode());
            $this->assertSame('=Catalogos!$C$2:$C$4', $sheet->getCell('H2')->getDataValidation()->getFormula1());
            $this->assertSame(['Zona 1', 'Zona 2', 'Zona 3'], array_column($book->getSheetByName('Catalogos')->rangeToArray('C2:C4'), 0));
            $this->assertSame(
                ['LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'TARIJA', 'SUCRE', 'TRINIDAD', 'COBIJA'],
                array_column($book->getSheetByName('Catalogos')->rangeToArray('B2:B10'), 0),
            );
            foreach (['A2', 'B2'] as $cell) {
                $validation = $sheet->getCell($cell)->getDataValidation();
                $this->assertSame('=Catalogos!$B$2:$B$10', $validation->getFormula1());
                $this->assertTrue($validation->getShowErrorMessage());
                $this->assertFalse($validation->getAllowBlank());
            }

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
                'volver_a_crear' => '1',
            ])->assertSessionHasNoErrors()
                ->assertSessionMissing('import_errors')
                ->assertSessionHas('success', 'Importación completada. Creadas: 1, actualizadas: 1.')
                ->assertRedirect(route('tarifa-contrato.create', ['tarifario_id' => $this->tarifario->id]));
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

    public function test_excel_import_rejects_non_catalog_departments_and_saves_valid_values_in_uppercase(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'tarifa-departamentos-');
        $book = new Spreadsheet;
        $book->getActiveSheet()->fromArray([
            ['origen', 'destino', 'servicio', 'kilo', 'kilo_extra', 'horas_entrega'],
            ['PANDO', 'LA PAZ', 'ENVIO NACIONAL (REGULAR)', 20, 5, 24],
            ['LA PAZ', 'BENI', 'ENVIO NACIONAL (REGULAR)', 20, 5, 24],
            [' la paz ', 'sucre', 'ENVIO NACIONAL (REGULAR)', 20, 5, 24],
        ]);

        try {
            (new Xlsx($book))->save($path);
            $this->post(route('tarifa-contrato.import'), [
                'tarifario_contrato_id' => $this->tarifario->id,
                'archivo' => new UploadedFile($path, 'tarifas.xlsx', null, null, true),
            ])->assertSessionHasNoErrors()->assertSessionHas('import_errors', function (array $errors): bool {
                return count($errors) === 2 && str_contains($errors[0], 'plantilla para origen')
                    && str_contains($errors[1], 'plantilla para destino');
            });
            $this->assertDatabaseCount('tarifa_contrato', 1);
            $this->assertDatabaseHas('tarifa_contrato', ['origen' => 'LA PAZ', 'destino' => 'SUCRE']);
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_excel_keeps_local_zones_separate_and_recognizes_legacy_zone_values(): void
    {
        $row = $this->row(['destino' => 'LA PAZ', 'servicio' => 'ENVIO LOCAL(REGULAR)']);
        $general = TarifaContrato::create($row);
        $zonaUno = TarifaContrato::create([...$row, 'zona' => 'ZONA 1']);
        DB::table('tarifa_contrato')->where('id', $zonaUno->id)->update(['zona' => 'zona1']);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['origen', 'destino', 'servicio', 'kilo', 'kilo_extra', 'horas_entrega', 'Zona (opcional)'],
            ['LA PAZ', 'LA PAZ', 'ENVIO LOCAL (REGULAR)', 14, 6, 24, '1'],
            ['LA PAZ', 'LA PAZ', 'ENVIO LOCAL (REGULAR)', 17, 6, 24, 'Zona 2'],
            ['LA PAZ', 'LA PAZ', 'ENVIO LOCAL (REGULAR)', 22, 6, 24, 'ZONA3'],
        ]);
        $path = tempnam(sys_get_temp_dir(), 'tarifa-zonas-');
        try {
            (new Xlsx($spreadsheet))->save($path);
            $this->post(route('tarifa-contrato.import'), [
                'tarifario_contrato_id' => $this->tarifario->id,
                'archivo' => new UploadedFile($path, 'tarifas.xlsx', null, null, true),
            ])->assertSessionHasNoErrors()->assertSessionMissing('import_errors');

            $this->assertDatabaseCount('tarifa_contrato', 4);
            $this->assertSame('20.50', $general->fresh()->kilo);
            $this->assertDatabaseHas('tarifa_contrato', ['id' => $zonaUno->id, 'zona' => 'Zona 1', 'kilo' => 14]);
            $this->assertDatabaseHas('tarifa_contrato', ['zona' => 'Zona 2', 'kilo' => 17]);
            $this->assertDatabaseHas('tarifa_contrato', ['zona' => 'Zona 3', 'kilo' => 22]);
        } finally {
            $spreadsheet->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_company_excel_exports_the_complete_active_tarifario_and_can_be_imported(): void
    {
        $historico = TarifarioContrato::create(['nombre' => 'Tarifario anterior', 'anio' => 2026]);
        TarifaContrato::create($this->row(['tarifario_contrato_id' => $historico->id, 'provincia' => 'HISTORICO']));
        app(TarifarioContratoService::class)->asignar($historico->id, [1, 2]);

        for ($number = 1; $number <= 16; $number++) {
            $destino = match ($number) {
                1 => 'COBIJA',
                2 => 'TRINIDAD',
                3 => 'SUCRE',
                default => 'COCHABAMBA',
            };
            TarifaContrato::create($this->row(['destino' => $destino, 'provincia' => 'PROVINCIA '.$number]));
        }
        $rate = $this->row([
            'destino' => 'LA PAZ', 'servicio' => 'ENVIO LOCAL(REGULAR)', 'zona' => 'Zona 2',
            'provincia_origen' => 'MURILLO', 'provincia' => 'MURILLO', 'direccion' => '=ZONA 2', 'retencion' => 1.25,
        ]);
        TarifaContrato::create([...$rate, 'peso' => 1.5, 'kilo' => 1234.56]);
        TarifaContrato::create([...$rate, 'peso' => 2, 'kilo' => 2345.67]);
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);

        $response = $this->get(route('tarifa-contrato.export', ['empresa_id' => 1, 'page' => 2, 'destino' => 'SUCRE']))
            ->assertDownload('tarifario-tarifario-a-2026.xlsx');

        $path = tempnam(sys_get_temp_dir(), 'tarifario-exportado-');
        $book = null;
        try {
            file_put_contents($path, $response->streamedContent());
            $book = IOFactory::load($path);
            $sheet = $book->getSheetByName('TarifaContrato');
            $this->assertSame('A1:L19', $sheet->getAutoFilter()->getRange());
            $this->assertSame('PROVINCIA 16', $sheet->getCell('J17')->getValue());
            $this->assertSame('Peso (kg) (opcional)', $sheet->getCell('L1')->getValue());
            $this->assertSame('=ZONA 2', $sheet->getCell('G2')->getValue());
            $this->assertSame('s', $sheet->getCell('G2')->getDataType());
            $this->assertSame('Zona 2', $sheet->getCell('H2')->getValue());
            $this->assertSame(1234.56, $sheet->getCell('D2')->getValue());
            $destinos = ['COBIJA', ...array_fill(0, 13, 'COCHABAMBA'), 'SUCRE', 'TRINIDAD'];
            $this->assertSame($destinos, array_column($sheet->rangeToArray('B4:B19'), 0));

            $summary = $book->getActiveSheet();
            $this->assertSame('Resumen', $summary->getTitle());
            $this->assertSame([['ENVIO LOCAL(REGULAR)', 2], ['ENVIO NACIONAL (REGULAR)', 16]], $summary->rangeToArray('A6:B7', null, true, false));
            $this->assertSame("sheet://'TarifaContrato'!A1", $summary->getCell('A3')->getHyperlink()->getUrl());
            $national = $book->getSheet(3);
            $this->assertSame("sheet://'".$national->getTitle()."'!A1", $summary->getCell('C7')->getHyperlink()->getUrl());
            $this->assertSame('ENVIO NACIONAL (REGULAR)', $national->getCell('A1')->getValue());
            $this->assertSame('Departamento de destino', $national->getCell('A3')->getValue());
            $this->assertSame($destinos, array_column($national->rangeToArray('A4:A19'), 0));
            $this->assertSame(array_fill(0, 16, 'LA PAZ'), array_column($national->rangeToArray('B4:B19'), 0));
            $this->assertSame('=Catalogos!$B$2:$B$10', $national->getCell('A4')->getDataValidation()->getFormula1());
            $this->assertSame('=Catalogos!$B$2:$B$10', $national->getCell('B4')->getDataValidation()->getFormula1());

            $importTarget = TarifarioContrato::create(['nombre' => 'Copia para importar', 'anio' => 2026]);
            $this->post(route('tarifa-contrato.import'), [
                'tarifario_contrato_id' => $importTarget->id,
                'archivo' => new UploadedFile($path, 'tarifario.xlsx', null, null, true),
            ])->assertSessionHasNoErrors()->assertSessionMissing('import_errors');
            $this->assertSame(18, $importTarget->tarifas()->count());
            $this->assertDatabaseHas('tarifa_contrato', [
                ...$rate, 'tarifario_contrato_id' => $importTarget->id, 'peso' => 1.5, 'kilo' => 1234.56,
            ]);
            $this->assertDatabaseHas('tarifa_contrato', [
                ...$rate, 'tarifario_contrato_id' => $importTarget->id, 'peso' => 2, 'kilo' => 2345.67,
            ]);
        } finally {
            $book?->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_excel_can_download_a_draft_and_rejects_an_unassigned_company(): void
    {
        TarifaContrato::create($this->row());
        $this->get(route('tarifa-contrato.export', ['tarifario_id' => $this->tarifario->id]))
            ->assertDownload('tarifario-tarifario-a-2026.xlsx');

        $this->getJson(route('tarifa-contrato.export', ['tarifario_id' => $this->tarifario->id, 'empresa_id' => 1]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('empresa_id');

        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);
        $request = Request::create(route('tarifa-contrato.index'), 'GET', ['empresa_id' => 1]);
        $view = app(TarifaContratoController::class)->index($request);
        $template = preg_replace('/@extends\([^\n]+\)/', '', file_get_contents($view->getPath()));
        $template = str_replace("@include('footer')", '', $template);
        $html = Blade::render($template." @yield('content')", $view->getData() + ['errors' => new ViewErrorBag]);
        $this->assertStringContainsString(route('tarifa-contrato.export', ['empresa_id' => 1]), $html);
        $this->assertStringContainsString('Descargar Excel', $html);
        $this->assertStringContainsString('Bs 20,50', $html);
        $this->assertStringContainsString('Ver detalles', $html);
    }

    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function test_import_permission_allows_an_upload_and_invalid_files_return_to_the_create_page(): void
    {
        $this->withMiddleware(EnsureRoutePermission::class);
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        Permission::findOrCreate('tarifa-contrato.import', 'web');
        $permission = Permission::findOrCreate('feature.tarifa-contrato.import', 'web');
        $user = (new User)->forceFill(['id' => 2]);
        $user->permissions()->attach($permission);

        $createUrl = route('tarifa-contrato.create', ['tarifario_id' => $this->tarifario->id]);
        $this->actingAs($user)->from($createUrl)->post(route('tarifa-contrato.import'), [
            'tarifario_contrato_id' => $this->tarifario->id,
            'archivo' => UploadedFile::fake()->create('tarifas.pdf', 1, 'application/pdf'),
            'volver_a_crear' => '1',
        ])->assertRedirect($createUrl)->assertSessionHasErrors('archivo');
        $this->assertDatabaseCount('tarifa_contrato', 0);
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
        $this->post(route('tarifa-contrato.store'), $this->row(['action' => 'save_and_new', 'origen' => ' potosi ', 'destino' => 'cobija']))
            ->assertSessionHasNoErrors()->assertRedirect(route('tarifa-contrato.create'));
        $this->assertDatabaseCount('tarifa_contrato', 1);
        $this->assertDatabaseHas('tarifa_contrato', ['origen' => 'POTOSI', 'destino' => 'COBIJA']);
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
            'empresa_titular_id' => 2, 'anio' => 2027, 'copiar_de_id' => $this->tarifario->id,
        ])->assertSessionHasNoErrors();
        $nuevo = TarifarioContrato::where('anio', 2027)->firstOrFail();
        $copy = $nuevo->tarifas()->firstOrFail();
        $this->assertSame(2, $nuevo->empresaTitular->id);
        $this->assertSame('Empresa dos', $nuevo->nombre);
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

    public function test_approval_protects_prices_and_keeps_the_current_company_assignment_until_replaced(): void
    {
        $tarifaActiva = TarifaContrato::create($this->row());
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);
        $nuevo = TarifarioContrato::create(['nombre' => 'Tarifario nuevo', 'anio' => 2027]);
        $urlAprobacion = route('tarifa-contrato.tarifarios-aprobar', $nuevo);

        $this->postJson($urlAprobacion)->assertUnprocessable()->assertJsonValidationErrors('tarifario_contrato_id');
        $this->assertNull($nuevo->fresh()->publicado_en);

        $tarifaNueva = TarifaContrato::create($this->row(['tarifario_contrato_id' => $nuevo->id, 'kilo' => 40]));
        $this->post($urlAprobacion)->assertSessionHasNoErrors()->assertRedirect(route('tarifa-contrato.tarifarios'));
        $aprobadoEn = $nuevo->fresh()->publicado_en;
        $this->assertNotNull($aprobadoEn);
        $this->assertSame([$tarifaActiva->id], TarifaContrato::paraEmpresa(1)->pluck('id')->all());
        $this->assertFalse($nuevo->empresasActivas()->exists());

        $view = app(TarifaContratoController::class)->tarifarios();
        $template = preg_replace('/@extends\([^\n]+\)/', '', file_get_contents($view->getPath()));
        $template = str_replace("@include('footer')", '', $template);
        $html = Blade::render($template." @yield('content')", $view->getData() + ['errors' => new ViewErrorBag]);
        $this->assertStringContainsString('tarifario-estado--aprobado">Aprobado</span>', $html);
        $this->assertStringContainsString('tarifario-estado--uso">En uso</span>', $html);
        $this->assertStringNotContainsString($urlAprobacion, $html);

        $this->travel(1)->minute();
        $this->post($urlAprobacion)->assertSessionHasNoErrors();
        $this->assertTrue($aprobadoEn->equalTo($nuevo->fresh()->publicado_en));
        $this->put(route('tarifa-contrato.update', $tarifaNueva), $this->row(['tarifario_contrato_id' => $nuevo->id, 'kilo' => 99]))
            ->assertSessionHasErrors('tarifario_contrato_id');
        $this->assertSame('40.00', $tarifaNueva->fresh()->kilo);
        $this->travelBack();

        session()->forget('errors');
        $this->post(route('tarifa-contrato.tarifarios-asignar', $nuevo), ['empresas' => [1]])->assertSessionHasNoErrors();
        $this->assertSame([$tarifaNueva->id], TarifaContrato::paraEmpresa(1)->pluck('id')->all());
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

    public function test_company_draft_stays_editable_until_approval_replaces_only_its_owners_active_rates(): void
    {
        $tarifaAnterior = TarifaContrato::create($this->row());
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1, 2]);
        $this->post(route('tarifa-contrato.tarifarios-store'), [
            'empresa_titular_id' => 1, 'nombre' => 'Nombre que se ignora', 'anio' => 2027,
        ])->assertSessionHasNoErrors();
        $nuevo = TarifarioContrato::where('anio', 2027)->sole();
        $this->assertSame('Empresa uno', $nuevo->nombre);
        $this->assertSame(1, $nuevo->empresaTitular->id);
        $this->assertFalse($nuevo->empresasActivas()->exists());
        $this->assertSame([$tarifaAnterior->id], TarifaContrato::paraEmpresa(1)->pluck('id')->all());

        $this->postJson(route('tarifa-contrato.tarifarios-aprobar', $nuevo))
            ->assertUnprocessable()->assertJsonValidationErrors('tarifario_contrato_id');
        $this->post(route('tarifa-contrato.store'), $this->row(['tarifario_contrato_id' => $nuevo->id, 'kilo' => 45]))
            ->assertSessionHasNoErrors();
        $tarifaNueva = $nuevo->tarifas()->sole();
        $this->assertSame([$tarifaAnterior->id], TarifaContrato::paraEmpresa(1)->pluck('id')->all());

        $this->post(route('tarifa-contrato.tarifarios-aprobar', $nuevo))->assertSessionHasNoErrors();
        $this->assertSame([$tarifaNueva->id], TarifaContrato::paraEmpresa(1)->pluck('id')->all());
        $this->assertSame([$tarifaAnterior->id], TarifaContrato::paraEmpresa(2)->pluck('id')->all());
        $this->assertDatabaseHas('tarifario_contrato_empresa', [
            'empresa_id' => 1, 'tarifario_contrato_id' => $this->tarifario->id, 'activo' => false,
        ]);
        $this->putJson(route('tarifa-contrato.tarifarios-update', $nuevo), ['empresa_titular_id' => 2, 'anio' => 2027])
            ->assertUnprocessable()->assertJsonValidationErrors('empresa_titular_id');

        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);
        $this->post(route('tarifa-contrato.tarifarios-aprobar', $nuevo))->assertSessionHasNoErrors();
        $this->assertSame([$tarifaAnterior->id], TarifaContrato::paraEmpresa(1)->pluck('id')->all());
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

    public function test_local_contract_uses_the_selected_zone_when_the_shipment_is_weighed(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->id());
        DB::table('users')->insert(['id' => 1]);
        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            foreach (['user_id', 'empresa_id', 'estados_id'] as $field) {
                $table->unsignedBigInteger($field);
            }
            $table->unsignedBigInteger('tarifa_contrato_id')->nullable();
            foreach (['codigo', 'cod_especial', 'origen', 'provincia_origen', 'destino', 'destino_registrado', 'nombre_r', 'telefono_r', 'contenido', 'cantidad', 'direccion_r', 'nombre_d', 'telefono_d', 'direccion_d', 'mapa', 'provincia', 'observacion', 'justificacion', 'imagen'] as $field) {
                $table->string($field)->nullable();
            }
            $table->decimal('peso', 10, 3);
            $table->decimal('precio', 10, 2)->nullable();
            $table->timestamp('fecha_recojo')->nullable();
            $table->timestamps();
        });
        foreach ([
            '2026_02_03_000002_create_estados_table.php',
            '2026_02_22_000009_create_eventos_table.php',
            '2026_02_26_212137_create_codigo_empresa_table.php',
            '2026_03_02_150000_create_eventos_contrato_table.php',
            '2026_07_31_120000_create_correlativos_contrato_and_unique_codigo.php',
        ] as $migration) {
            (require database_path('migrations/'.$migration))->up();
        }
        DB::table('estados')->insert(['id' => 1, 'nombre_estado' => 'SOLICITUD']);
        DB::table('eventos')->insert(['id' => 318, 'nombre_evento' => 'SOLICITUD']);

        $servicio = 'ENVIO LOCAL(REGULAR)';
        $rate = $this->row(['destino' => 'LA PAZ', 'servicio' => $servicio, 'kilo_extra' => 6]);
        $zonaUno = TarifaContrato::create([...$rate, 'zona' => '1', 'kilo' => 14]);
        DB::table('tarifa_contrato')->where('id', $zonaUno->id)->update(['zona' => 'ZONA 1']);
        $zonaDos = TarifaContrato::create([...$rate, 'zona' => '2', 'kilo' => 17]);
        TarifaContrato::create([...$rate, 'zona' => '3', 'provincia' => 'MURILLO', 'kilo' => 22]);
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);

        $user = auth()->user();
        $user->forceFill(['empresa_id' => 1, 'ciudad' => 'LA PAZ']);
        $user->shouldReceive('can')->with('feature.paquetes-contrato.create-con-tarifa.create')->andReturnTrue();
        $view = app(RecojoController::class)->createConTarifa();
        $zonas = $view->getData()['zonasLocales'][$servicio]['LA PAZ'];
        $this->assertEqualsCanonicalizing(['Zona 1', 'Zona 2'], $zonas['']);
        $this->assertSame(['Zona 3'], $zonas['MURILLO']);

        $payload = [
            'servicio' => $servicio, 'destino' => 'LA PAZ',
            'nombre_r' => 'Remitente', 'telefono_r' => '70000001', 'contenido' => 'Documentos', 'direccion_r' => 'Calle A',
            'nombre_d' => 'Destinatario', 'telefono_d' => '70000002', 'direccion' => 'Calle B',
        ];
        $url = route('paquetes-contrato.store-con-tarifa');
        $this->post($url, $payload)->assertSessionHasErrors('zona');
        $this->post($url, [...$payload, 'zona' => 'ZONA 3'])->assertSessionHasErrors('zona');
        $this->assertDatabaseCount('paquetes_contrato', 0);
        session()->forget('errors');
        $this->post($url, [...$payload, 'zona' => 'zona2'])->assertSessionHasNoErrors()
            ->assertRedirect(route('paquetes-contrato.index'));

        $shipment = Recojo::firstOrFail();
        $this->assertSame($zonaDos->id, $shipment->tarifa_contrato_id);
        $this->assertNull($shipment->precio);
        $component = new class extends PaquetesEms
        {
            public function price(Recojo $shipment): bool
            {
                return $this->applyTarifaContratoPricing($shipment, allowAutoResolve: true);
            }
        };
        $shipment->peso = 2;
        $this->assertTrue($component->price($shipment));
        $this->assertSame('23.00', $shipment->fresh()->precio);

        $shipment->tarifa_contrato_id = null;
        $this->assertFalse($component->price($shipment));
        $this->assertNull($shipment->fresh()->precio);
    }

    public function test_migration_preserves_existing_tariff_ids_and_company_access_without_guessing_the_year(): void
    {
        $legacy = TarifaContrato::create($this->row(['empresa_id' => 1]));
        $migration = require database_path('migrations/2026_11_16_120000_create_tarifarios_contrato.php');
        $services = require database_path('migrations/2026_11_16_130000_create_servicios_contrato.php');
        $titular = require database_path('migrations/2026_11_19_120000_add_empresa_titular_to_tarifarios_contrato.php');
        $titular->down();
        $services->down();
        $migration->down();
        $migration->up();
        $services->up();
        $titular->up();
        $preserved = TarifaContrato::paraEmpresa(1)->firstOrFail();
        $this->assertSame($legacy->id, $preserved->id);
        $this->assertSame('20.50', $preserved->kilo);
        $this->assertNull($preserved->tarifario->anio);
        $this->assertNotNull($preserved->tarifario->publicado_en);
    }

    public function test_requires_a_registered_company_and_prevents_duplicate_schedules_for_the_same_company_and_year(): void
    {
        $url = route('tarifa-contrato.tarifarios-store');
        $this->postJson($url, ['nombre' => 'Tarifario A'])->assertUnprocessable()
            ->assertJsonValidationErrors(['empresa_titular_id', 'anio']);
        $this->postJson($url, ['empresa_titular_id' => 999, 'anio' => 2027])->assertUnprocessable()
            ->assertJsonValidationErrors('empresa_titular_id');
        $this->post($url, ['empresa_titular_id' => 1, 'anio' => 2027])->assertSessionHasNoErrors();
        $this->postJson($url, ['empresa_titular_id' => 1, 'anio' => 2027])->assertUnprocessable()
            ->assertJsonValidationErrors('anio');

        DB::table('empresa')->where('id', 2)->update(['nombre' => 'Empresa uno']);
        $this->post($url, ['empresa_titular_id' => 2, 'anio' => 2027])->assertSessionHasNoErrors();
        $this->assertSame([1, 2], TarifarioContrato::where('anio', 2027)->orderBy('empresa_titular_id')->pluck('empresa_titular_id')->all());
    }

    public function test_existing_unambiguous_company_drafts_are_linked_without_activating_them(): void
    {
        $titular = require database_path('migrations/2026_11_19_120000_add_empresa_titular_to_tarifarios_contrato.php');
        $titular->down();
        $borrador = TarifarioContrato::create(['nombre' => 'EMPRESA UNO', 'anio' => 2027]);
        $compartido = TarifarioContrato::create(['nombre' => 'Empresa dos', 'anio' => 2027]);
        TarifaContrato::create($this->row(['tarifario_contrato_id' => $compartido->id]));
        app(TarifarioContratoService::class)->asignar($compartido->id, [1]);
        $titular->up();

        $this->assertSame(1, $borrador->fresh()->empresaTitular->id);
        $this->assertFalse($borrador->empresasActivas()->exists());
        $this->assertNull($compartido->fresh()->empresa_titular_id);
        $this->assertSame([1], $compartido->empresasActivas()->pluck('empresa.id')->all());
    }

    public function test_administration_views_render_the_year_and_current_assignments(): void
    {
        TarifaContrato::create($this->row());
        app(TarifarioContratoService::class)->asignar($this->tarifario->id, [1]);
        $borrador = TarifarioContrato::create(['nombre' => 'Tarifario pendiente', 'anio' => 2027]);
        TarifaContrato::create($this->row(['tarifario_contrato_id' => $borrador->id]));
        foreach (['tarifarios', 'index', 'importForm', 'servicios'] as $method) {
            $controller = app(TarifaContratoController::class);
            $view = in_array($method, ['index', 'servicios'], true) ? $controller->$method(request()) : $controller->$method();
            $template = file_get_contents($view->getPath());
            $template = preg_replace('/@extends\([^\n]+\)/', '', $template);
            $template = str_replace("@include('footer')", '', $template);
            $html = Blade::render($template." @yield('content') @stack('js')", $view->getData() + ['errors' => new ViewErrorBag]);
            $this->assertStringContainsString('Tarifario', $html);
            if ($method === 'tarifarios') {
                $this->assertStringNotContainsString('<th>Estado</th>', $html);
                $documento = new \DOMDocument();
                @$documento->loadHTML('<meta charset="utf-8">'.$html);
                $xpath = new \DOMXPath($documento);
                $this->assertSame('Nombre de la empresa *', $xpath->evaluate('string(//label[@for="empresa_titular_id"])'));
                $empresas = $xpath->query('//select[@id="empresa_titular_id" and @name="empresa_titular_id" and @required]/option[@value!=""]');
                $this->assertSame([2 => 'Empresa dos · 002', 1 => 'Empresa uno · 001'], collect(iterator_to_array($empresas))
                    ->mapWithKeys(fn ($opcion) => [$opcion->getAttribute('value') => trim($opcion->textContent)])->all());
                $this->assertStringNotContainsString('name="nombre"', $html);
                $this->assertStringContainsString('tarifario-estado--borrador">Borrador</span>', $html);
                $this->assertStringContainsString(route('tarifa-contrato.tarifarios-aprobar', $borrador), $html);
                $this->assertStringContainsString('Aprobar uso', $html);
                $this->assertSame(1, $xpath->query('//button[@data-aprobar-tarifario and @type="button" and @data-nombre="Tarifario pendiente" and @data-anio="2027" and @data-cantidad="1"]')->length);
                $this->assertSame(1, $xpath->query('//dialog[@id="tarifario-aprobacion"]//button[@data-aprobacion-confirmar]')->length);
                $this->assertSame(1, $xpath->query('//button[@data-abrir-acciones="tarifario-acciones-'.$borrador->id.'"]')->length);
                $this->assertSame(1, $xpath->query('//dialog[@id="tarifario-acciones-'.$borrador->id.'"]//button[@data-aprobar-tarifario]')->length);
            }
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
        $this->post(route('tarifa-contrato.tarifarios-store'), ['empresa_titular_id' => 1, 'anio' => 2027])->assertForbidden();
        $this->post(route('tarifa-contrato.tarifarios-aprobar', $this->tarifario))->assertForbidden();
        $this->post(route('tarifa-contrato.tarifarios-asignar', $this->tarifario), ['empresas' => [1]])->assertForbidden();
        $this->post(route('tarifa-contrato.servicios-store'), ['nombre' => 'SIN PERMISO', 'anio' => 2026])->assertForbidden();
        $this->assertDatabaseCount('tarifarios_contrato', 1);
        $this->assertDatabaseCount('tarifario_contrato_empresa', 0);
    }

    public function test_preloading_a_tarifario_copies_all_edited_rows_to_an_independent_destination(): void
    {
        $servicio = 'SERVICIO LOCAL ENTEL';
        ServicioContrato::asegurar($servicio, 2026);
        for ($number = 1; $number <= 261; $number++) {
            TarifaContrato::create($this->row([
                'destino' => 'LA PAZ', 'servicio' => $servicio, 'zona' => 'Zona 2', 'provincia' => 'PROVINCIA '.$number,
                'peso' => $number === 1 ? 1.5 : null,
            ]));
        }
        $destino = TarifarioContrato::create(['nombre' => 'Otra empresa', 'anio' => 2027]);

        $request = Request::create(route('tarifa-contrato.create'), 'GET', ['tarifario_origen_id' => $this->tarifario->id]);
        $request->setLaravelSession(app('session.store'));
        $view = app(TarifaContratoController::class)->create($request)->getData();
        $filas = $view['filasPrecargadas'];
        $this->assertCount(261, $filas);
        $this->assertSame([$destino->id], $view['tarifarios']->pluck('id')->all());
        $this->assertSame('1.50', $filas[0]['peso']);
        $this->assertArrayNotHasKey('tarifario_contrato_id', $filas[0]);
        $this->assertFalse(ServicioContrato::deAnio(2027)->where('nombre', $servicio)->exists());

        $filas[0]['kilo'] = '27,50';
        $payload = [
            'tarifario_origen_id' => $this->tarifario->id,
            'tarifario_contrato_id' => $destino->id,
            'tarifas_serializadas' => json_encode($filas, JSON_THROW_ON_ERROR),
        ];
        $this->post(route('tarifa-contrato.store'), $payload)
            ->assertSessionHasNoErrors()->assertRedirect(route('tarifa-contrato.index', ['tarifario_id' => $destino->id]));

        $this->assertSame(261, $destino->tarifas()->count());
        $this->assertSame(261, $this->tarifario->tarifas()->count());
        $this->assertSame('20.50', $this->tarifario->tarifas()->orderBy('id')->firstOrFail()->kilo);
        $copia = $destino->tarifas()->orderBy('id')->firstOrFail();
        $this->assertSame('27.50', $copia->kilo);
        $this->assertSame('1.50', $copia->peso);
        $this->assertSame('Zona 2', $copia->zona);
        $this->assertSame(2027, $copia->servicioCatalogo->anio);

        $this->post(route('tarifa-contrato.store'), $payload)->assertSessionHasNoErrors();
        $this->assertSame(261, $destino->tarifas()->count());
    }

    public function test_preloaded_rows_cannot_be_saved_back_into_the_source_or_create_unrelated_services(): void
    {
        $origen = TarifaContrato::create($this->row());
        $payload = [
            'tarifario_origen_id' => $this->tarifario->id,
            'tarifario_contrato_id' => $this->tarifario->id,
            'tarifas_serializadas' => json_encode([$this->row(['kilo' => 50])], JSON_THROW_ON_ERROR),
        ];
        $this->postJson(route('tarifa-contrato.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('tarifario_contrato_id');
        $this->assertSame('20.50', $origen->fresh()->kilo);

        $destino = TarifarioContrato::create(['nombre' => 'Otra empresa', 'anio' => 2027]);
        $this->postJson(route('tarifa-contrato.store'), [
            ...$payload, 'tarifario_contrato_id' => $destino->id,
            'tarifas_serializadas' => json_encode([$this->row(['servicio' => 'INVENTADO'])], JSON_THROW_ON_ERROR),
        ])->assertUnprocessable()->assertJsonValidationErrors('tarifas.0.servicio');
        $this->assertDatabaseCount('tarifa_contrato', 1);
        $this->assertFalse(ServicioContrato::query()->where('nombre', 'INVENTADO')->exists());
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
        $this->post(route('tarifa-contrato.tarifarios-store'), ['empresa_titular_id' => 1, 'anio' => 2027, 'copiar_de_id' => $this->tarifario->id])->assertSessionHasNoErrors();
        $copy = TarifarioContrato::where('empresa_titular_id', 1)->firstOrFail()->tarifas()->firstOrFail();
        $this->assertSame(2027, $copy->servicioCatalogo->anio);
        $this->assertSame(2026, $original->fresh()->servicioCatalogo->anio);
        $this->assertSame($original->kilo, $copy->kilo);
        $this->assertNotSame($original->servicio_contrato_id, $copy->servicio_contrato_id);
    }

    public function test_defining_the_year_of_an_existing_tariff_rebinds_services_without_changing_prices(): void
    {
        $tarifa = TarifaContrato::create($this->row());
        $this->put(route('tarifa-contrato.tarifarios-update', $this->tarifario), ['empresa_titular_id' => 1, 'anio' => 2028])->assertSessionHasNoErrors();
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
        $this->post(route('tarifa-contrato.tarifarios-store'), ['empresa_titular_id' => 1, 'anio' => 2029])->assertSessionHasNoErrors();
        $this->assertSame(0, ServicioContrato::deAnio(2029)->count());
        ServicioContrato::asegurar('SERVICIO DEFINIDO PARA 2029', 2029);
        $names = $this->getJson(route('tarifa-contrato.servicios', ['anio' => 2029]))->assertOk()->json('servicios');
        $this->assertSame(['SERVICIO DEFINIDO PARA 2029'], $names);

        TarifaContrato::create($this->row());
        $this->post(route('tarifa-contrato.tarifarios-store'), ['empresa_titular_id' => 2, 'anio' => 2030, 'copiar_de_id' => $this->tarifario->id])->assertSessionHasNoErrors();
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
