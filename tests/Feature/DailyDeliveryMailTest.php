<?php

namespace Tests\Feature;

use App\Mail\DailyDeliveryMail;
use App\Models\AppSetting;
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

        (require database_path('migrations/0001_01_01_000001_create_cache_table.php'))->up();
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
            $table->string('email')->nullable();
            $table->string('ciudad');
            $table->json('regionales')->nullable();
            $table->integer('empresa_id')->nullable();
            $table->softDeletes();
        });
        (require database_path('migrations/2025_06_11_195557_create_permission_tables.php'))->up();
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_evento');
        });
        foreach (['ems' => ['estado_id', 'ciudad'], 'contrato' => ['estados_id', 'destino']] as $module => [$state, $destination]) {
            Schema::create('paquetes_'.$module, function (Blueprint $table) use ($state, $destination) {
                $table->string('codigo');
                $table->integer($state)->nullable();
                $table->string($destination)->nullable();
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
            ['id' => 3, 'name' => 'encargado_ems', 'guard_name' => 'web'],
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
        $this->assertStringEndsWith('| NACIONAL', $message->getSubject());
        $this->assertStringContainsString('Consolidado nacional', $message->getHtmlBody());
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

    public function test_inactive_couriers_are_hidden_even_when_they_registered_movements(): void
    {
        $today = '2026-11-20 12:00:00';
        DB::table('users')->where('id', 2)->update(['deleted_at' => '2026-11-20 13:00:00']);
        DB::table('paquetes_ems')->insert(['codigo' => 'local', 'ciudad' => 'LA PAZ', 'created_at' => $today]);
        $this->movement('ems', 'local', 500, 2, $today);
        $this->movement('ems', 'local', CarteroEvent::ENTREGADO_ID, 2, $today);

        $report = app(DailyDeliveryReport::class)->forDate('2026-11-20');

        $this->assertSame(['registered' => 1, 'assigned' => 1, 'delivered' => 1], $report['totals']);
        $this->assertSame(['Ana', 'María'], $report['couriers']->pluck('name')->all());

        $this->actingAs(User::findOrFail(4))
            ->get(route('daily-deliveries-email.index', ['date' => '2026-11-20', 'regional' => 'LA PAZ']))
            ->assertOk()
            ->assertSee('Regional LA PAZ')
            ->assertSee('Ana')
            ->assertDontSee('Luis');
    }

    public function test_regional_reports_limit_packages_events_and_couriers_and_label_the_attachments(): void
    {
        $today = '2026-11-20 12:00:00';
        DB::table('paquetes_ems')->insert([
            ['codigo' => 'local', 'ciudad' => ' la paz ', 'created_at' => $today],
            ['codigo' => 'other', 'ciudad' => 'ORURO', 'created_at' => $today],
            ['codigo' => 'unknown', 'ciudad' => null, 'created_at' => $today],
        ]);
        DB::table('paquetes_contrato')->insert([
            ['codigo' => 'province', 'destino' => 'TRINIDAD-RIBERALTA', 'created_at' => $today],
            ['codigo' => 'accent', 'destino' => 'Potosí', 'created_at' => $today],
        ]);
        $this->movement('ems', 'local', 500, 1, $today);
        $this->movement('ems', 'local', CarteroEvent::ENTREGADO_ID, 3, $today);
        $this->movement('ems', 'other', CarteroEvent::ENTREGADO_ID, 1, $today);
        $this->movement('ems', 'unknown', 500, 1, $today);
        $this->movement('contrato', 'province', CarteroEvent::ENTREGADO_ID, 3, $today);

        $reports = app(DailyDeliveryReport::class);
        $report = $reports->forDate('2026-11-20', 'LA PAZ');
        $this->assertSame(['registered' => 1, 'assigned' => 1, 'delivered' => 1], $report['totals']);
        $this->assertSame(['Ana', 'Luis'], $report['couriers']->pluck('name')->all());
        $this->assertSame(0, $report['couriers']->first()['delivered']);
        $this->assertSame(['name' => 'Contratos', 'registered' => 1, 'assigned' => 0, 'delivered' => 1], $reports->forDate('2026-11-20', 'TRINIDAD')['modules']['contratos']);
        $this->assertSame(1, $reports->forDate('2026-11-20', 'POTOSI')['totals']['registered']);

        $message = Mail::mailer('array')->to('regional@example.com')->send(new DailyDeliveryMail($report))->getOriginalMessage();
        $this->assertStringEndsWith('| LA PAZ', $message->getSubject());
        $this->assertStringContainsString('Regional LA PAZ', $message->getHtmlBody());
        $this->assertStringNotContainsString('María', $message->getHtmlBody());
        $this->assertSame(['entregas-diarias-2026-11-20-la-paz.pdf', 'entregas-diarias-2026-11-20-la-paz.xlsx'], collect($message->getAttachments())->map->getFilename()->all());
    }

    public function test_manual_and_automatic_mail_use_the_regional_role_list_and_the_daily_send_is_idempotent(): void
    {
        Mail::fake();
        app(DailyClosingMailService::class)->saveRecipients(['closing@example.com']);
        AppSetting::setValue('operations.daily_deliveries_email_recipients', '["legacy@example.com"]');
        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        Mail::assertNothingSent();

        $this->encargado(5, 'regional@example.com', 'TARIJA', ['LA PAZ', 'ORURO']);
        $this->encargado(6, 'ORURO@example.com', 'ORURO');
        $this->encargado(7, 'ignored@example.com', 'ORURO', deletedAt: '2026-11-19 12:00:00');
        $this->encargado(8, 'missing@example.com', '');
        $this->encargado(9, 'ORURO@example.com', 'ORURO');
        $this->encargado(10, 'invalid-address', 'ORURO');
        DB::table('users')->where('id', 1)->update(['email' => 'courier@example.com']);

        $this->actingAs(User::findOrFail(1))
            ->post(route('daily-deliveries-email.send'), ['date' => '2026-11-19'])
            ->assertForbidden();

        $this->actingAs(User::findOrFail(4))
            ->get(route('daily-deliveries-email.index', ['date' => '2026-11-19', 'regional' => 'LA PAZ']))
            ->assertOk()
            ->assertSee('Destinatarios por regional')
            ->assertSee('oruro@example.com')
            ->assertSee('Regional LA PAZ')
            ->assertDontSee('ignored@example.com');
        $this->post(route('daily-deliveries-email.send'), ['date' => '2026-11-19'])
            ->assertRedirect(route('daily-deliveries-email.index', ['date' => '2026-11-19']));
        $this->post(route('daily-deliveries-email.send'), ['date' => '2026-11-21'])
            ->assertSessionHasErrors('date');

        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();

        Mail::assertSent(DailyDeliveryMail::class, 6);
        Mail::assertSent(DailyDeliveryMail::class, fn (DailyDeliveryMail $mail) => $mail->hasTo('regional@example.com') && $mail->report['regional'] === 'LA PAZ' && $mail->report['date'] === '2026-11-19');
        Mail::assertSent(DailyDeliveryMail::class, fn (DailyDeliveryMail $mail) => $mail->hasTo('regional@example.com') && $mail->report['regional'] === 'ORURO' && $mail->report['cutoff'] === '20/11/2026 20:00:00');
        $this->assertSame([
            'LA PAZ' => ['regional@example.com'],
            'ORURO' => ['regional@example.com', 'oruro@example.com'],
        ], app(DailyDeliveryMailService::class)->sentOn('2026-11-20'));
        $this->assertSame(['closing@example.com'], app(DailyClosingMailService::class)->recipients());

        $this->patch(route('daily-deliveries-email.update'), ['enabled' => 0])->assertSessionHas('status');
        $this->assertFalse(app(DailyDeliveryMailService::class)->automaticSendingEnabled());
        $this->encargado(11, 'new@example.com', 'ORURO');
        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        Mail::assertSent(DailyDeliveryMail::class, 6);
    }

    public function test_admins_can_manage_both_lists_and_each_scope_receives_its_own_report(): void
    {
        Mail::fake();
        $today = '2026-11-20 12:00:00';
        DB::table('paquetes_ems')->insert([
            ['codigo' => 'local', 'ciudad' => 'LA PAZ', 'created_at' => $today],
            ['codigo' => 'other', 'ciudad' => 'ORURO', 'created_at' => $today],
        ]);
        $this->encargado(5, 'encargado@example.com', 'LA PAZ');
        $store = route('daily-deliveries-email.recipients.store');
        $destroy = route('daily-deliveries-email.recipients.destroy');
        $national = ['scope' => 'NACIONAL', 'recipient' => 'national@example.com', 'name' => 'Destinatario nacional'];
        $regional = ['scope' => 'LA PAZ', 'recipient' => 'regional@example.com'];

        $this->actingAs(User::findOrFail(1))->post($store, $national)->assertForbidden();
        $this->actingAs(User::findOrFail(4))->from(route('daily-deliveries-email.index'));
        $this->post($store, ['scope' => 'DESCONOCIDA', 'recipient' => 'invalido'])->assertSessionHasErrors(['scope', 'recipient']);
        $this->post($store, $national)->assertSessionHas('status');
        $this->post($store, $regional)->assertSessionHas('status');
        $this->assertSame('Destinatario nacional', app(DailyDeliveryMailService::class)->recipients()['NACIONAL']['national@example.com']['name']);
        $this->post($store, ['scope' => 'NACIONAL', 'recipient' => 'NATIONAL@example.com'])->assertSessionHasErrors('recipient');
        $this->post($store, ['scope' => 'LA PAZ', 'recipient' => 'encargado@example.com'])->assertSessionHasErrors('recipient');
        $this->delete($destroy, ['scope' => 'LA PAZ', 'recipient' => 'encargado@example.com'])->assertSessionHasErrors('recipient');

        $this->get(route('daily-deliveries-email.index'))
            ->assertOk()
            ->assertSeeInOrder(['Destinatarios por regional', 'encargado@example.com', 'regional@example.com', 'Agregar correo regional', 'Destinatarios nacionales', 'Destinatario nacional', 'national@example.com', 'Agregar correo nacional']);

        $this->post(route('daily-deliveries-email.send'), ['date' => '2026-11-20'])->assertSessionHas('status');
        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        Mail::assertSent(DailyDeliveryMail::class, 6);

        $nationalMail = Mail::sent(DailyDeliveryMail::class, fn (DailyDeliveryMail $mail) => $mail->hasTo('national@example.com'));
        $this->assertCount(2, $nationalMail);
        foreach ($nationalMail as $mail) {
            $this->assertNull($mail->report['regional']);
            $this->assertSame(2, $mail->report['totals']['registered']);
        }

        $regionalMail = Mail::sent(DailyDeliveryMail::class, fn (DailyDeliveryMail $mail) => $mail->hasTo('regional@example.com'));
        $this->assertCount(2, $regionalMail);
        foreach ($regionalMail as $mail) {
            $this->assertSame('LA PAZ', $mail->report['regional']);
            $this->assertSame(1, $mail->report['totals']['registered']);
        }

        $this->delete($destroy, $national)->assertSessionHas('status');
        $this->delete($destroy, $regional)->assertSessionHas('status');
        $this->get(route('daily-deliveries-email.index'))
            ->assertOk()
            ->assertSee('encargado@example.com')
            ->assertDontSee('national@example.com')
            ->assertDontSee('regional@example.com');
    }

    public function test_automatic_retry_sends_only_to_recipients_whose_previous_delivery_failed(): void
    {
        $this->encargado(5, 'retry@example.com', 'LA PAZ');
        $this->encargado(6, 'success@example.com', 'LA PAZ');
        $report = \Mockery::on(fn (array $report) => $report['regional'] === 'LA PAZ');
        $mail = \Mockery::mock(DailyDeliveryMailService::class, [app(DailyDeliveryReport::class)])->makePartial();
        $this->app->instance(DailyDeliveryMailService::class, $mail);
        $mail->shouldReceive('send')->once()->ordered()->with('retry@example.com', $report)
            ->andThrow(new RuntimeException('Simulated SMTP failure'));
        $mail->shouldReceive('send')->once()->ordered()->with('success@example.com', $report);
        $mail->shouldReceive('send')->once()->ordered()->with('retry@example.com', $report);

        $this->artisan('operations:send-daily-deliveries')->assertFailed();
        $this->assertSame(['LA PAZ' => ['success@example.com']], $mail->sentOn('2026-11-20'));

        $this->artisan('operations:send-daily-deliveries')->assertSuccessful();
        $this->assertSame(['LA PAZ' => ['success@example.com', 'retry@example.com']], $mail->sentOn('2026-11-20'));
    }

    private function encargado(int $id, string $email, string $city, ?array $regionals = null, ?string $deletedAt = null): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'name' => 'Encargado '.$id,
            'email' => $email,
            'ciudad' => $city,
            'regionales' => $regionals === null ? null : json_encode($regionals),
            'deleted_at' => $deletedAt,
        ]);
        DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => User::class, 'model_id' => $id]);
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
