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
}
