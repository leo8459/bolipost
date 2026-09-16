<?php

namespace Tests\Feature;

use App\Mail\DailyClosingMail;
use App\Models\AppSetting;
use App\Services\ContractExpirationMailService;
use App\Services\DailyClosingMailService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
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

    public function test_report_counts_daily_events_and_accumulated_pending_without_old_assignments(): void
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
        $this->assertSame(2, $contracts['pending']->count());
        $this->assertSame(1, $contracts['unassigned']);
        $this->assertSame([['name' => 'Luis', 'pending' => 1]], $contracts['couriers']->all());
        $this->assertSame(2, (int) $contracts['activity']->first()->movimientos);
        $this->assertSame(2, $report['modules'][1]['pending']->count());
        $mail = new DailyClosingMail($report);
        $this->assertStringContainsString('Sin movimientos registrados hoy', $mail->render());
        $this->assertCount(1, $mail->attachments());
    }

    public function test_manual_send_does_not_suppress_evening_send_and_command_avoids_duplicate(): void
    {
        Mail::fake();
        app(ContractExpirationMailService::class)->saveRecipients(['admin@example.com']);
        $service = app(DailyClosingMailService::class);
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
        app(ContractExpirationMailService::class)->saveRecipients(['admin@example.com']);
        app(DailyClosingMailService::class)->setAutomaticSendingEnabled(false);
        $this->artisan('operations:send-daily-closing')->assertSuccessful();
        Mail::assertNothingSent();
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
        $this->assertSame(3, $report['modules'][1]['pending']->count());
    }

    public function test_excel_separates_departments_preserves_codes_and_includes_every_pending(): void
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
            ['codigo' => '00123', 'evento_id' => 295, 'user_id' => 1, 'created_at' => '2026-09-15 11:00:00'],
            ['codigo' => '00123', 'evento_id' => 300, 'user_id' => 1, 'created_at' => '2026-09-16 21:00:00'],
        ]);
        DB::table('eventos_contrato')->insert(['codigo' => 'CON-1', 'evento_id' => 295, 'created_at' => '2026-09-16 11:00:00']);
        $report = app(DailyClosingMailService::class)->report();
        $bytes = \Maatwebsite\Excel\Facades\Excel::raw(new \App\Exports\DailyClosingExport($report), \Maatwebsite\Excel\Excel::XLSX);
        $path = tempnam(sys_get_temp_dir(), 'closing-');
        try {
            file_put_contents($path, $bytes);
            $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
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
        } finally {
            unlink($path);
        }
    }
}
