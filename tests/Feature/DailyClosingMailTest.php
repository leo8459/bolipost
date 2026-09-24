<?php

namespace Tests\Feature;

use App\Exports\DailyClosingExport;
use App\Http\Controllers\ContractExpirationEmailController;
use App\Http\Controllers\ReportesController;
use App\Mail\DailyClosingMail;
use App\Models\AppSetting;
use App\Services\ContractExpirationMailService;
use App\Services\DailyClosingMailService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class DailyClosingMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-16 20:00', 'America/La_Paz'));
        Schema::create('app_settings', function (Blueprint $t) {
            $t->id();
            $t->string('key')->unique();
            $t->text('value')->nullable();
            $t->timestamps();
        });
        Schema::create('estados', function (Blueprint $t) {
            $t->id();
            $t->string('nombre_estado');
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
        });
        Schema::create('eventos', function (Blueprint $t) {
            $t->id();
            $t->string('nombre_evento');
        });
        foreach (['paquetes_contrato' => 'estados_id', 'paquetes_ems' => 'estado_id'] as $table => $state) {
            Schema::create($table, function (Blueprint $t) use ($state) {
                $t->id();
                $t->string('codigo');
                $t->integer($state)->nullable();
                $t->string('destino')->nullable();
                $t->string('origen')->nullable();
                $t->string('provincia_origen')->nullable();
                $t->string('provincia')->nullable();
                $t->string('ciudad')->nullable();
                $t->timestamps();
            });
        }
        foreach (['eventos_contrato', 'eventos_ems'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->id();
                $t->string('codigo');
                $t->integer('evento_id');
                $t->integer('user_id')->nullable();
                $t->timestamps();
            });
        }
        Schema::create('cartero', function (Blueprint $t) {
            $t->id();
            $t->integer('id_paquetes_contrato')->nullable();
            $t->integer('id_paquetes_ems')->nullable();
            $t->integer('id_user')->nullable();
            $t->integer('id_estados');
        });
        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'CARTERO'], ['id' => 2, 'nombre_estado' => 'ENTREGADO'],
            ['id' => 3, 'nombre_estado' => 'CANCELADO'], ['id' => 4, 'nombre_estado' => 'SOLICITUD'],
        ]);
        DB::table('users')->insert([['id' => 1, 'name' => 'Ana'], ['id' => 2, 'name' => 'Luis']]);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_report_only_counts_and_lists_movements_inside_the_daily_cutoff(): void
    {
        foreach (['paquetes_contrato' => 'estados_id', 'paquetes_ems' => 'estado_id'] as $table => $state) {
            foreach ([1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 1] as $id => $status) {
                DB::table($table)->insert(['id' => $id, 'codigo' => 'PK-'.$id, $state => $status,
                    'created_at' => $id === 4 ? '2026-09-15 10:00:00' : ($id === 5 ? '2026-09-16 21:00:00' : '2026-09-16 10:00:00')]);
            }
        }
        DB::table('cartero')->insert([
            ['id_paquetes_contrato' => 1, 'id_user' => 1, 'id_estados' => 1],
            ['id_paquetes_contrato' => 1, 'id_user' => 2, 'id_estados' => 1],
            ['id_paquetes_contrato' => 2, 'id_user' => 1, 'id_estados' => 1],
        ]);
        DB::table('eventos')->insert(['id' => 316, 'nombre_evento' => 'Entregado']);
        foreach (['2026-09-16 12:00:00', '2026-09-16 13:00:00', '2026-09-16 21:00:00'] as $date) {
            DB::table('eventos_contrato')->insert(['codigo' => 'PK-2', 'evento_id' => 316, 'created_at' => $date]);
        }
        $report = app(DailyClosingMailService::class)->report();
        $contracts = $report['modules'][0];
        $this->assertSame(3, $contracts['registered']);
        $this->assertSame(1, $contracts['delivered']);
        $this->assertCount(2, $contracts['movements']);
        $this->assertSame(1, $contracts['moved_packages']);
        $this->assertSame(2, (int) $contracts['activity']->first()->movimientos);
        $this->assertCount(0, $report['modules'][1]['movements']);
        $mail = new DailyClosingMail($report);
        $this->assertStringContainsString('Sin movimientos registrados en la fecha elegida', $mail->render());
        $this->assertCount(1, $mail->attachments());
    }

    public function test_manual_send_does_not_suppress_evening_send_and_command_avoids_duplicate(): void
    {
        Mail::fake();
        $service = app(DailyClosingMailService::class);
        $service->saveRecipients(['admin@example.com']);
        $service->send(['admin@example.com']);
        $this->assertNull(AppSetting::getValue(DailyClosingMailService::LAST_SENT_SETTING));
        $this->artisan('operations:send-daily-closing')->assertSuccessful();
        $this->artisan('operations:send-daily-closing')->assertSuccessful();
        Mail::assertSent(DailyClosingMail::class, 2);
        $this->assertSame('2026-09-16', AppSetting::getValue(DailyClosingMailService::LAST_SENT_SETTING));
    }

    public function test_disabled_automatic_send_and_missing_recipients_send_nothing(): void
    {
        Mail::fake();
        $this->artisan('operations:send-daily-closing')->assertSuccessful();
        $service = app(DailyClosingMailService::class);
        $service->saveRecipients(['admin@example.com']);
        $service->setAutomaticSendingEnabled(false);
        $this->artisan('operations:send-daily-closing')->assertSuccessful();
        Mail::assertNothingSent();
    }

    public function test_manual_controller_sends_the_selected_date(): void
    {
        Mail::fake();
        app(DailyClosingMailService::class)->saveRecipients(['admin@example.com']);
        $request = Request::create('/administrador/correo-electronico/cierre-diario/enviar', 'POST', [
            'date' => '2026-09-15',
        ]);

        app(ContractExpirationEmailController::class)->sendDailyClosing(
            $request,
            app(DailyClosingMailService::class)
        );

        Mail::assertSent(DailyClosingMail::class, fn (DailyClosingMail $mail) => $mail->report['date'] === '2026-09-15');
    }

    public function test_daily_closing_recipients_are_independent_from_contract_recipients(): void
    {
        $contractService = app(ContractExpirationMailService::class);
        $closingService = app(DailyClosingMailService::class);

        $contractService->saveRecipients(['contracts@example.com']);
        $closingService->saveRecipients(['closing@example.com']);

        $this->assertSame(['contracts@example.com'], $contractService->recipients());
        $this->assertSame(['closing@example.com'], $closingService->recipients());

        $request = Request::create(
            '/administrador/correo-electronico/cierre-diario/destinatarios',
            'POST',
            ['recipient' => 'other-closing@example.com']
        );
        app(ContractExpirationEmailController::class)
            ->addDailyClosingRecipient($request, $closingService);

        $this->assertSame(['contracts@example.com'], $contractService->recipients());
        $this->assertSame(
            ['closing@example.com', 'other-closing@example.com'],
            $closingService->recipients()
        );
    }

    public function test_migration_copies_existing_recipients_only_as_an_independent_initial_list(): void
    {
        $contractService = app(ContractExpirationMailService::class);
        $closingService = app(DailyClosingMailService::class);
        $contractService->saveRecipients(['existing@example.com']);

        $migration = require database_path('migrations/2026_09_24_000000_separate_daily_closing_email_recipients.php');
        $migration->up();

        $this->assertSame(['existing@example.com'], $closingService->recipients());

        $closingService->saveRecipients(['closing-only@example.com']);

        $this->assertSame(['existing@example.com'], $contractService->recipients());
        $this->assertSame(['closing-only@example.com'], $closingService->recipients());
    }

    public function test_bolivian_day_is_converted_to_the_storage_timezone(): void
    {
        config(['app.timezone' => 'UTC']);
        DB::table('paquetes_ems')->insert([
            ['codigo' => 'YESTERDAY', 'created_at' => '2026-09-16 03:59:59'],
            ['codigo' => 'TODAY', 'created_at' => '2026-09-16 04:00:00'],
            ['codigo' => 'AT-CUTOFF', 'created_at' => '2026-09-17 00:00:00'],
            ['codigo' => 'AFTER-CUTOFF', 'created_at' => '2026-09-17 00:00:01'],
        ]);
        $report = app(DailyClosingMailService::class)->report();
        $this->assertSame('2026-09-16', $report['date']);
        $this->assertSame(2, $report['modules'][1]['registered']);
        $this->assertCount(0, $report['modules'][1]['movements']);
    }

    public function test_a_manually_selected_day_uses_the_complete_selected_day(): void
    {
        DB::table('paquetes_ems')->insert(['codigo' => 'EMS-1', 'ciudad' => 'La Paz', 'created_at' => '2026-09-15 08:00:00']);
        DB::table('eventos')->insert(['id' => 300, 'nombre_evento' => 'En tránsito']);
        DB::table('eventos_ems')->insert([
            ['codigo' => 'EMS-1', 'evento_id' => 300, 'created_at' => '2026-09-15 23:30:00'],
            ['codigo' => 'EMS-1', 'evento_id' => 300, 'created_at' => '2026-09-16 09:00:00'],
        ]);

        $report = app(DailyClosingMailService::class)->reportForDate('2026-09-15');

        $this->assertSame('2026-09-15', $report['date']);
        $this->assertCount(1, $report['modules'][1]['movements']);
        $this->assertSame('15/09/2026 23:30:00', $report['modules'][1]['movements']->first()->event_date);
    }

    public function test_lifetime_report_combines_and_filters_contract_and_ems_movements(): void
    {
        DB::table('eventos')->insert(['id' => 300, 'nombre_evento' => 'En tránsito']);
        DB::table('paquetes_contrato')->insert(['codigo' => 'CON-1', 'destino' => 'Sucre', 'created_at' => '2026-09-10 08:00:00']);
        DB::table('paquetes_ems')->insert(['codigo' => 'EMS-1', 'ciudad' => 'La Paz', 'created_at' => '2026-09-10 08:00:00']);
        DB::table('eventos_contrato')->insert(['codigo' => 'CON-1', 'evento_id' => 300, 'user_id' => 1, 'created_at' => '2026-09-11 10:00:00']);
        DB::table('eventos_ems')->insert(['codigo' => 'EMS-1', 'evento_id' => 300, 'user_id' => 2, 'created_at' => '2026-09-12 10:00:00']);

        $controller = app(ReportesController::class);
        $allResponse = $controller->lifetimeMovements(
            Request::create('/reportes/movimiento-toda-la-vida', 'GET')
        );
        $allData = $allResponse->getData();
        $this->assertSame(2, $allData['movements']->total());
        $this->assertSame(1, (int) $allData['summary']->get('Contratos')->movements);
        $this->assertSame(1, (int) $allData['summary']->get('EMS')->movements);

        $response = $controller->lifetimeMovements(
            Request::create('/reportes/movimiento-toda-la-vida', 'GET', ['service' => 'ems'])
        );
        $data = $response->getData();

        $this->assertSame(1, $data['movements']->total());
        $this->assertSame('EMS-1', $data['movements']->first()->code);
        $this->assertSame(1, (int) $data['summary']->get('EMS')->movements);
    }

    public function test_excel_separates_origin_departments_and_only_includes_movements_from_the_day(): void
    {
        DB::table('paquetes_ems')->insert([
            ['codigo' => '00123', 'origen' => 'COCHABAMBA', 'ciudad' => 'La Paz', 'created_at' => '2026-09-16 10:00:00'],
            ['codigo' => '=1+1', 'origen' => null, 'ciudad' => 'Sucre', 'created_at' => '2026-09-16 10:00:00'],
            ['codigo' => 'UNKNOWN', 'origen' => null, 'ciudad' => 'Otro destino', 'created_at' => '2026-09-16 10:00:00'],
        ]);
        DB::table('paquetes_contrato')->insert([
            'codigo' => 'CON-1', 'origen' => 'SANTA CRUZ', 'provincia_origen' => 'ANDRÉS IBÁÑEZ', 'destino' => 'Potosí', 'provincia' => 'TOMÁS FRÍAS', 'estados_id' => 4, 'created_at' => '2026-09-16 10:00:00',
        ]);
        DB::table('eventos')->insert([
            ['id' => 295, 'nombre_evento' => 'Recogido'],
            ['id' => 300, 'nombre_evento' => 'En tránsito'],
        ]);
        DB::table('eventos_ems')->insert([
            ['codigo' => '00123', 'evento_id' => 300, 'user_id' => 2, 'created_at' => '2026-09-16 12:00:00'],
            ['codigo' => '00123', 'evento_id' => 295, 'user_id' => 1, 'created_at' => '2026-09-16 13:00:00'],
            ['codigo' => '00123', 'evento_id' => 295, 'user_id' => 1, 'created_at' => '2026-09-15 11:00:00'],
            ['codigo' => '00123', 'evento_id' => 300, 'user_id' => 1, 'created_at' => '2026-09-16 21:00:00'],
        ]);
        DB::table('eventos_contrato')->insert(['codigo' => 'CON-1', 'evento_id' => 295, 'created_at' => '2026-09-16 11:00:00']);
        $report = app(DailyClosingMailService::class)->report();
        $bytes = \Maatwebsite\Excel\Facades\Excel::raw(new DailyClosingExport($report), Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'closing-');
        try {
            file_put_contents($path, $bytes);
            $book = IOFactory::load($path);
            $this->assertSame(['Resumen', 'COCHABAMBA', 'SANTA CRUZ'], $book->getSheetNames());
            $this->assertSame('00123', $book->getSheetByName('COCHABAMBA')->getCell('B2')->getValue());
            $this->assertSame('Contratos', $book->getSheetByName('SANTA CRUZ')->getCell('A2')->getValue());
            $this->assertSame(
                "1) 16/09/2026 12:00:00 · En tránsito · Luis\n2) 16/09/2026 13:00:00 · Recogido · Ana",
                $book->getSheetByName('COCHABAMBA')->getCell('G2')->getValue()
            );
            $this->assertSame(2, collect(array_slice($book->getAllSheets(), 1))->sum(fn ($sheet) => $sheet->getHighestDataRow() - 1));
            $this->assertSame('cierre-diario-2026-09-16.xlsx', (new DailyClosingMail($report))->attachments()[0]->as);
            $book->disconnectWorksheets();

            /* Expectations for the former accumulated-pending workbook:
            $this->assertSame(['Resumen', ...\App\Exports\DailyClosingExport::DEPARTMENTS, 'SIN DEPARTAMENTO', 'Historial'], $book->getSheetNames());
            $this->assertSame('00123', $book->getSheetByName('LA PAZ')->getCell('B2')->getValue());
            $this->assertSame('Origen', $book->getSheetByName('LA PAZ')->getCell('C1')->getValue());
            $this->assertSame('COCHABAMBA', $book->getSheetByName('LA PAZ')->getCell('C2')->getValue());
            $this->assertSame('SANTA CRUZ', $book->getSheetByName('POTOSI')->getCell('C2')->getValue());
            $cell = $book->getSheetByName('CHUQUISACA')->getCell('B2');
            $this->assertSame('=1+1', $cell->getValue());
            $this->assertSame(\PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING, $cell->getDataType());
            $this->assertSame('Contratos', $book->getSheetByName('POTOSI')->getCell('A2')->getValue());
            $this->assertSame('UNKNOWN', $book->getSheetByName('SIN DEPARTAMENTO')->getCell('B2')->getValue());
            $this->assertSame('Provincia de origen', $book->getSheetByName('POTOSI')->getCell('D1')->getValue());
            $this->assertSame('Provincia de destino', $book->getSheetByName('POTOSI')->getCell('F1')->getValue());
            $this->assertSame('ANDRÉS IBÁÑEZ', $book->getSheetByName('POTOSI')->getCell('D2')->getValue());
            $this->assertSame('TOMÁS FRÍAS', $book->getSheetByName('POTOSI')->getCell('F2')->getValue());
            $this->assertSame('Sin provincia registrada', $book->getSheetByName('LA PAZ')->getCell('D2')->getValue());
            $this->assertSame('Sin provincia registrada', $book->getSheetByName('LA PAZ')->getCell('F2')->getValue());
            $this->assertSame('ANDRÉS IBÁÑEZ', $book->getSheetByName('Historial')->getCell('D2')->getValue());
            $this->assertSame('TOMÁS FRÍAS', $book->getSheetByName('Historial')->getCell('F2')->getValue());
            $this->assertSame("15/09/2026 11:00:00 · Recogido (Ana)\n16/09/2026 12:00:00 · En tránsito (Luis)", $book->getSheetByName('LA PAZ')->getCell('J2')->getValue());
            $this->assertSame('Recogido (Usuario no disponible)', $book->getSheetByName('Historial')->getCell('G2')->getValue());
            $this->assertSame('Recogido (Ana)', $book->getSheetByName('Historial')->getCell('G3')->getValue());
            $this->assertSame('En tránsito (Luis)', $book->getSheetByName('Historial')->getCell('G4')->getValue());
            $this->assertSame('Sin eventos registrados', $book->getSheetByName('SIN DEPARTAMENTO')->getCell('J2')->getValue());
            $this->assertSame(4, $book->getSheetByName('Historial')->getHighestDataRow());
            $total = 0;
            foreach (array_slice($book->getAllSheets(), 1) as $sheet) {
                if ($sheet->getTitle() === 'Historial') {
                    continue;
                }
                $total += $sheet->getHighestDataRow() - 1;
            }
            $this->assertSame(4, $total);
            $this->assertSame('cierre-diario-2026-09-16.xlsx', (new DailyClosingMail($report))->attachments()[0]->as);
            $book->disconnectWorksheets();
            */
        } finally {
            unlink($path);
        }
    }
}
