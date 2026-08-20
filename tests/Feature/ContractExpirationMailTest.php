<?php

namespace Tests\Feature;

use App\Mail\ContractExpirationAlertMail;
use App\Models\AppSetting;
use App\Services\ContractExpirationMailService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContractExpirationMailTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-19 09:00:00');

        Schema::dropIfExists('app_settings');
        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::dropIfExists('empresa');
        Schema::create('empresa', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre');
            $table->date('fin_contrato')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('empresa');
        Schema::dropIfExists('app_settings');

        parent::tearDown();
    }

    public function test_it_sends_upcoming_contracts_to_each_saved_recipient(): void
    {
        Mail::fake();

        DB::table('empresa')->insert([
            'nombre' => 'UPRE',
            'fin_contrato' => '2026-08-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = app(ContractExpirationMailService::class);
        $service->saveRecipients(['contratos@example.com', 'admin@example.com']);

        $sent = $service->send($service->recipients());

        $this->assertSame(2, $sent);
        $this->assertSame('2026-08-19', AppSetting::getValue(ContractExpirationMailService::LAST_SENT_SETTING));
        Mail::assertSent(ContractExpirationAlertMail::class, 2);
        Mail::assertSent(ContractExpirationAlertMail::class, function (ContractExpirationAlertMail $mail): bool {
            return $mail->hasTo('contratos@example.com')
                && $mail->alerts->pluck('empresa')->all() === ['UPRE'];
        });
    }

    public function test_automatic_sending_is_enabled_by_default_and_can_be_disabled(): void
    {
        $service = app(ContractExpirationMailService::class);

        $this->assertTrue($service->automaticSendingEnabled());

        $service->setAutomaticSendingEnabled(false);

        $this->assertFalse($service->automaticSendingEnabled());
        $this->assertSame(
            '0',
            AppSetting::getValue(ContractExpirationMailService::AUTOMATIC_SENDING_ENABLED_SETTING)
        );
    }

    public function test_scheduled_command_does_not_send_when_automatic_sending_is_disabled(): void
    {
        Mail::fake();

        $service = app(ContractExpirationMailService::class);
        $service->saveRecipients(['contratos@example.com']);
        $service->setAutomaticSendingEnabled(false);

        $this->artisan('contracts:send-expiration-alerts', ['--force' => true])
            ->expectsOutput('El envio automatico de avisos de contratos esta desactivado.')
            ->assertSuccessful();

        Mail::assertNothingSent();
    }
}
