<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CancelExpiredPickupRequestsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 9, 17)->setTime(10, 0));

        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('paquetes_contrato', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('estados_id')->nullable();
            $table->timestamps();
        });
        DB::table('estados')->insert([
            ['id' => 4, 'nombre_estado' => ' solicitud '],
            ['id' => 29, 'nombre_estado' => 'CANCELADO'],
            ['id' => 7, 'nombre_estado' => 'RECOGIDO'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('estados');
        $this->travelBack();
        parent::tearDown();
    }

    public function test_only_requests_older_than_twenty_days_are_cancelled_and_reruns_are_safe(): void
    {
        DB::table('paquetes_contrato')->insert([
            ['id' => 1, 'estados_id' => 4, 'created_at' => now()->subDays(20)->subSecond(), 'updated_at' => now()],
            ['id' => 2, 'estados_id' => 4, 'created_at' => now()->subDays(20), 'updated_at' => now()],
            ['id' => 3, 'estados_id' => 4, 'created_at' => now()->subDays(19), 'updated_at' => now()],
            ['id' => 4, 'estados_id' => 7, 'created_at' => now()->subDays(30), 'updated_at' => now()],
            ['id' => 5, 'estados_id' => 29, 'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(5)],
            ['id' => 6, 'estados_id' => null, 'created_at' => now()->subDays(30), 'updated_at' => now()],
        ]);

        $this->artisan('contracts:cancel-expired-pickups')
            ->expectsOutput('Envios cancelados automaticamente: 1')->assertSuccessful();
        foreach ([1 => 29, 2 => 4, 3 => 4, 4 => 7, 5 => 29, 6 => null] as $id => $state) {
            $this->assertDatabaseHas('paquetes_contrato', ['id' => $id, 'estados_id' => $state]);
        }
        $this->assertDatabaseHas('paquetes_contrato', ['id' => 1, 'created_at' => now()->subDays(20)->subSecond()->toDateTimeString()]);
        $this->assertDatabaseHas('paquetes_contrato', ['id' => 5, 'updated_at' => now()->subDays(5)->toDateTimeString()]);
        $this->artisan('contracts:cancel-expired-pickups')
            ->expectsOutput('Envios cancelados automaticamente: 0')->assertSuccessful();
    }

    public function test_missing_cancelled_state_leaves_requests_untouched(): void
    {
        DB::table('estados')->where('id', 29)->delete();
        DB::table('paquetes_contrato')->insert(['id' => 1, 'estados_id' => 4, 'created_at' => now()->subDays(21)]);
        $this->artisan('contracts:cancel-expired-pickups')->assertFailed();
        $this->assertDatabaseHas('paquetes_contrato', ['id' => 1, 'estados_id' => 4]);
    }

    public function test_dry_run_counts_expired_requests_without_cancelling(): void
    {
        DB::table('paquetes_contrato')->insert(['id' => 1, 'estados_id' => 4, 'created_at' => now()->subDays(21)]);
        $this->artisan('contracts:cancel-expired-pickups --dry-run')
            ->expectsOutput('Envios pendientes de cancelacion: 1')->assertSuccessful();
        $this->assertDatabaseHas('paquetes_contrato', ['id' => 1, 'estados_id' => 4]);
    }
}
