<?php

namespace Tests\Feature;

use App\Http\Controllers\UserLoginLogController;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserLoginLogControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('alias')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('empresa_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload')->nullable();
            $table->integer('last_activity')->index();
        });

        Schema::create('user_login_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('user_name')->nullable();
            $table->string('user_alias')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->timestamp('logged_in_at')->nullable();
            $table->timestamp('logged_out_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Schema::dropIfExists('user_login_logs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    public function test_history_can_be_filtered_by_day_and_hour(): void
    {
        $this->insertLogin('Antes', '2026-08-10 08:59:59');
        $this->insertLogin('Dentro', '2026-08-10 09:30:00');
        $this->insertLogin('Después', '2026-08-10 10:01:00');
        $this->insertLogin('Otro día', '2026-08-11 09:30:00');

        $view = app(UserLoginLogController::class)->index(Request::create('/ingresos', 'GET', [
            'period' => 'day',
            'day' => '2026-08-10',
            'time_from' => '09:00',
            'time_to' => '10:00',
        ]));

        $this->assertSame(['Dentro'], $view->getData()['logs']->pluck('user_name')->all());
    }

    public function test_history_can_be_filtered_by_month(): void
    {
        $this->insertLogin('Julio', '2026-07-31 23:59:59');
        $this->insertLogin('Agosto uno', '2026-08-01 00:00:00');
        $this->insertLogin('Agosto dos', '2026-08-31 23:59:59');

        $view = app(UserLoginLogController::class)->index(Request::create('/ingresos', 'GET', [
            'period' => 'month',
            'month' => '2026-08',
        ]));

        $this->assertEqualsCanonicalizing(
            ['Agosto uno', 'Agosto dos'],
            $view->getData()['logs']->pluck('user_name')->all(),
        );
    }

    public function test_control_only_reports_active_sessions_without_an_active_user(): void
    {
        Carbon::setTestNow('2026-08-10 12:00:00');

        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Activo', 'alias' => 'activo', 'email' => 'activo@example.test', 'deleted_at' => null],
            ['id' => 2, 'name' => 'Dado de baja', 'alias' => 'baja', 'email' => 'baja@example.test', 'deleted_at' => now()],
        ]);

        DB::table('sessions')->insert([
            ['id' => 'active-user', 'user_id' => 1, 'last_activity' => now()->subMinute()->timestamp],
            ['id' => 'soft-deleted-user', 'user_id' => 2, 'last_activity' => now()->subMinute()->timestamp],
            ['id' => 'missing-user', 'user_id' => 999, 'last_activity' => now()->subMinutes(10)->timestamp],
            ['id' => 'expired-missing-user', 'user_id' => 998, 'last_activity' => now()->subMinutes(121)->timestamp],
        ]);

        $this->insertLogin('Dado de baja', '2026-08-10 11:00:00', 'soft-deleted-user', 2);
        $this->insertLogin('Desconocido', '2026-08-10 11:10:00', 'missing-user', 999);

        $view = app(UserLoginLogController::class)->index(Request::create('/ingresos', 'GET', [
            'view' => 'unregistered',
        ]));
        $data = $view->getData();

        $this->assertSame(2, $data['unregisteredCount']);
        $this->assertEqualsCanonicalizing(
            ['soft-deleted-user', 'missing-user'],
            $data['unregisteredSessions']->pluck('id')->all(),
        );
    }

    private function insertLogin(
        string $name,
        string $loggedInAt,
        ?string $sessionId = null,
        ?int $userId = null,
    ): void {
        DB::table('user_login_logs')->insert([
            'user_id' => $userId,
            'user_name' => $name,
            'user_alias' => mb_strtolower(str_replace(' ', '-', $name)),
            'session_id' => $sessionId,
            'logged_in_at' => $loggedInAt,
            'created_at' => $loggedInAt,
            'updated_at' => $loggedInAt,
        ]);
    }
}
