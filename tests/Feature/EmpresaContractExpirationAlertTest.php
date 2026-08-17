<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\EmpresaContractUserSyncService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class EmpresaContractExpirationAlertTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-08-14 12:00:00');
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

        parent::tearDown();
    }

    public function test_contracts_role_receives_all_upcoming_company_expiration_alerts(): void
    {
        DB::table('empresa')->insert([
            $this->empresaRow('UPRE', '2026-08-31'),
            $this->empresaRow('SAFI', '2026-10-02'),
            $this->empresaRow('VENCIDA', '2026-08-01'),
            $this->empresaRow('LEJANA', '2026-12-31'),
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('isSuperAdmin')->andReturnFalse();
        $user->shouldReceive('hasRole')->andReturnUsing(
            fn (string $role): bool => $role === 'contratos'
        );
        $user->shouldReceive('hasAnyRole')->with(['contratos'])->andReturnTrue();

        $alerts = app(EmpresaContractUserSyncService::class)
            ->buildExpirationAlertsForUser($user);

        $this->assertSame(['UPRE', 'SAFI'], $alerts->pluck('empresa')->all());
        $this->assertStringContainsString('La empresa UPRE', $alerts->first()['message']);
    }

    private function empresaRow(string $nombre, string $finContrato): array
    {
        return [
            'nombre' => $nombre,
            'fin_contrato' => $finContrato,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
