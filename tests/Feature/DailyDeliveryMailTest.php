<?php

namespace Tests\Feature;

use App\Mail\DailyDeliveryMail;
use App\Models\User;
use App\Services\DailyClosingMailService;
use App\Services\DailyDeliveryMailService;
use App\Services\DailyDeliveryReport;
use App\Support\CarteroEvent;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Tests\TestCase;

class DailyDeliveryMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['app.timezone' => DailyDeliveryReport::TIMEZONE, 'acl.route_permission.enabled' => false]);
        $this->travelTo(CarbonImmutable::parse('2026-11-20 20:15:00', DailyDeliveryReport::TIMEZONE));

        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
        Schema::create('empresa', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
        });
        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('ciudad');
            $table->integer('empresa_id')->nullable();
            $table->softDeletes();
        });
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_evento');
        });
        foreach (['ems' => 'estado_id', 'contrato' => 'estados_id'] as $module => $state) {
            Schema::create('paquetes_'.$module, function (Blueprint $table) use ($state) {
                $table->string('codigo');
                $table->integer($state)->nullable();
                $table->integer('empresa_id')->nullable();
                $table->integer('user_id')->nullable();
                $table->timestamp('created_at');
            });
            Schema::create('eventos_'.$module, function (Blueprint $table) {
                $table->string('codigo');
                $table->integer('evento_id');
                $table->integer('user_id')->nullable();
                $table->string('detalle_evento')->nullable();
                $table->timestamp('created_at');
            });
        }

        DB::table('estados')->insert(['id' => 9, 'nombre_estado' => 'CANCELADO']);
        DB::table('empresa')->insert(['id' => 5, 'nombre' => 'Empresa de prueba']);
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Ana', 'ciudad' => 'LA PAZ'],
            ['id' => 2, 'name' => 'Luis', 'ciudad' => 'LA PAZ'],
            ['id' => 3, 'name' => 'María', 'ciudad' => 'ORURO'],
            ['id' => 4, 'name' => 'Operador', 'ciudad' => 'LA PAZ'],
        ]);
        DB::table('roles')->insert([
            ['id' => 1, 'name' => 'cartero_ems', 'guard_name' => 'web'],
            ['id' => 2, 'name' => config('acl.super_admin_role'), 'guard_name' => 'web'],
        ]);
        foreach ([1 => 1, 2 => 1, 3 => 1, 4 => 2] as $userId => $roleId) {
            DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $userId]);
        }
        DB::table('eventos')->insert([
            ['id' => 500, 'nombre_evento' => CarteroEvent::ASIGNADO],
            ['id' => 501, 'nombre_evento' => CarteroEvent::CAMBIADO],
        ]);
    }

    public function test_the_report_counts_day_operations_and_includes_couriers_with_zero_activity(): void
    {
        $today = '2026-11-20 12:00:00';
        DB::table('paquetes_ems')->insert([
            ['codigo' => 'new', 'estado_id' => 1, 'created_at' => $today],
            ['codigo' => 'older', 'estado_id' => 1, 'created_at' => '2026-11-19 12:00:00'],
            ['codigo' => 'canceled', 'estado_id' => 9, 'created_at' => $today],
        ]);
        DB::table('paquetes_contrato')->insert([
            ['codigo' => 'contract', 'estados_id' => null, 'empresa_id' => null, 'created_at' => $today],
            ['codigo' => 'test-contract', 'estados_id' => 1, 'empresa_id' => 5, 'created_at' => $today],
        ]);
        $this->movement('ems', 'new', 500, 1, $today);
        $this->movement('ems', 'new', 500, 1, $today);
        $this->movement('ems', 'new', 501, 4, $today, 'Cambio de cartero realizado por Operador. De Ana a Luis.');
        $this->movement('ems', 'older', CarteroEvent::ENTREGADO_ID, 1, $today);
        $this->movement('ems', 'older', CarteroEvent::ENTREGADO_ID, 1, $today);
        $this->movement('ems', 'canceled', CarteroEvent::ENTREGADO_ID, 1, $today);
        $this->movement('contrato', 'test-contract', 500, 1, $today);
        $this->movement('contrato', 'contract', 500, 2, '2026-11-19 23:59:59');
        $this->movement('contrato', 'contract', CarteroEvent::ENTREGADO_ID, 2, '2026-11-20 20:05:00');

        $report = app(DailyDeliveryReport::class)->through(CarbonImmutable::parse('2026-11-20 20:00:00', DailyDeliveryReport::TIMEZONE));
        $couriers = $report['couriers']->keyBy('name');

        $this->assertSame(['registered' => 2, 'assigned' => 1, 'delivered' => 1], $report['totals']);
        $this->assertSame(['Ana', 'Luis', 'María'], $couriers->keys()->all());
        $this->assertSame(['assigned' => 1, 'delivered' => 1], $couriers['Ana']['modules']['ems']);
        $this->assertSame(['assigned' => 1, 'delivered' => 0], $couriers['Luis']['modules']['ems']);
        $this->assertSame(['assigned' => 0, 'delivered' => 0], $couriers['María']['modules']['contratos']);

        $mail = new DailyDeliveryMail($report);
        $message = Mail::mailer('array')->to('deliveries@example.com')->send($mail)->getOriginalMessage();
        $this->assertStringContainsString('Comparativo por servicio', $message->getHtmlBody());
        $attachments = collect($message->getAttachments())->keyBy(fn ($attachment) => $attachment->getFilename());
        $this->assertSame(['entregas-diarias-2026-11-20.pdf', 'entregas-diarias-2026-11-20.xlsx'], $attachments->keys()->all());
        $pdf = $attachments['entregas-diarias-2026-11-20.pdf'];
        $this->assertSame('application/pdf', $pdf->getContentType());
        $this->assertStringStartsWith('%PDF-', $pdf->getBody());

        $path = tempnam(sys_get_temp_dir(), 'daily-deliveries-');
        try {
            file_put_contents($path, $attachments['entregas-diarias-2026-11-20.xlsx']->getBody());
            Cell::setValueBinder(new DefaultValueBinder());
            $workbook = IOFactory::load($path);
            $this->assertSame(['Resumen', 'Carteros'], $workbook->getSheetNames());
            $this->assertSame(2, $workbook->getSheet(0)->getCell('B5')->getValue());
            $this->assertSame(0, $workbook->getSheet(1)->getCell('H4')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_manual_and_automatic_mail_use_an_independent_list_and_the_daily_send_is_idempotent(): void
    {
        Mail::fake();
        app(DailyClosingMailService::class)->saveRecipients(['closing@example.com']);
        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        Mail::assertNothingSent();

        $this->actingAs(User::findOrFail(1))
            ->post(route('daily-deliveries-email.recipients.store'), ['recipient' => 'deliveries@example.com'])
            ->assertForbidden();

        $this->actingAs(User::findOrFail(4))
            ->post(route('daily-deliveries-email.recipients.store'), ['recipient' => 'deliveries@example.com'])
            ->assertSessionHas('status');
        $this->post(route('daily-deliveries-email.send'), ['date' => '2026-11-19'])
            ->assertRedirect(route('daily-deliveries-email.index', ['date' => '2026-11-19']));
        $this->post(route('daily-deliveries-email.send'), ['date' => '2026-11-21'])
            ->assertSessionHasErrors('date');

        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();

        Mail::assertSent(DailyDeliveryMail::class, 2);
        Mail::assertSent(DailyDeliveryMail::class, fn (DailyDeliveryMail $mail) => $mail->hasTo('deliveries@example.com') && $mail->report['date'] === '2026-11-19');
        Mail::assertSent(DailyDeliveryMail::class, fn (DailyDeliveryMail $mail) => $mail->hasTo('deliveries@example.com') && $mail->report['cutoff'] === '20/11/2026 20:00:00');
        $this->assertSame(['closing@example.com'], app(DailyClosingMailService::class)->recipients());

        $this->patch(route('daily-deliveries-email.update'), ['enabled' => 0])->assertSessionHas('status');
        $this->assertFalse(app(DailyDeliveryMailService::class)->automaticSendingEnabled());
        $this->delete(route('daily-deliveries-email.recipients.destroy'), ['recipient' => 'deliveries@example.com'])
            ->assertSessionHas('status');
        $this->assertSame([], app(DailyDeliveryMailService::class)->recipients());
    }

    public function test_automatic_retry_sends_only_to_recipients_whose_previous_delivery_failed(): void
    {
        $report = \Mockery::type('array');
        $mail = $this->partialMock(DailyDeliveryMailService::class);
        $mail->saveRecipients(['retry@example.com', 'success@example.com']);
        $mail->shouldReceive('send')->once()->ordered()->with(['retry@example.com'], $report)
            ->andThrow(new RuntimeException('Simulated SMTP failure'));
        $mail->shouldReceive('send')->once()->ordered()->with(['success@example.com'], $report)->andReturn(1);
        $mail->shouldReceive('send')->once()->ordered()->with(['retry@example.com'], $report)->andReturn(1);

        $this->artisan('operations:send-daily-deliveries')->assertFailed();
        $this->assertSame(['success@example.com'], $mail->sentOn('2026-11-20'));

        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        $this->assertSame(['success@example.com', 'retry@example.com'], $mail->sentOn('2026-11-20'));
    }

    private function movement(string $module, string $code, int $event, int $user, string $date, ?string $detail = null): void
    {
        DB::table('eventos_'.$module)->insert([
            'codigo' => $code,
            'evento_id' => $event,
            'user_id' => $user,
            'created_at' => $date,
            'detalle_evento' => $detail,
        ]);
    }
}
