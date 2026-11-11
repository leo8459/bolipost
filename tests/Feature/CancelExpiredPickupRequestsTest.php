<?php

namespace Tests\Feature;

use App\Livewire\EventosTabla;
use App\Models\User;
use App\Support\AutomaticCancellationEvent;
use App\Support\EncargadoEvent;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
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
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });
        Schema::create('paquetes_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->foreignId('user_id')->default(7)->constrained('users');
            $table->unsignedBigInteger('estados_id')->nullable();
            $table->timestamps();
        });
        Schema::create('eventos_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->foreignId('evento_id')->constrained('eventos');
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });
        DB::table('users')->insert(['id' => 7, 'name' => 'Empresa']);
        DB::table('estados')->insert([
            ['id' => 4, 'nombre_estado' => ' solicitud '],
            ['id' => 29, 'nombre_estado' => 'CANCELADO'],
            ['id' => 7, 'nombre_estado' => 'RECOGIDO'],
        ]);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('eventos_contrato');
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('eventos');
        Schema::dropIfExists('users');
        Schema::dropIfExists('estados');
        Schema::dropIfExists('model_has_roles');
        Schema::dropIfExists('roles');
        $this->travelBack();
        parent::tearDown();
    }

    public function test_only_requests_older_than_twenty_days_are_cancelled_and_reruns_are_safe(): void
    {
        DB::table('paquetes_contrato')->insert([
            ['id' => 1, 'codigo' => 'CONT-1', 'estados_id' => 4, 'created_at' => now()->subDays(20)->subSecond(), 'updated_at' => now()],
            ['id' => 2, 'codigo' => 'CONT-2', 'estados_id' => 4, 'created_at' => now()->subDays(20), 'updated_at' => now()],
            ['id' => 3, 'codigo' => 'CONT-3', 'estados_id' => 4, 'created_at' => now()->subDays(19), 'updated_at' => now()],
            ['id' => 4, 'codigo' => 'CONT-4', 'estados_id' => 7, 'created_at' => now()->subDays(30), 'updated_at' => now()],
            ['id' => 5, 'codigo' => 'CONT-5', 'estados_id' => 29, 'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(5)],
            ['id' => 6, 'codigo' => 'CONT-6', 'estados_id' => null, 'created_at' => now()->subDays(30), 'updated_at' => now()],
        ]);

        $this->artisan('contracts:cancel-expired-pickups')
            ->expectsOutput('Envios cancelados automaticamente: 1')->assertSuccessful();
        foreach ([1 => 29, 2 => 4, 3 => 4, 4 => 7, 5 => 29, 6 => null] as $id => $state) {
            $this->assertDatabaseHas('paquetes_contrato', ['id' => $id, 'estados_id' => $state]);
        }
        $this->assertDatabaseHas('paquetes_contrato', ['id' => 1, 'created_at' => now()->subDays(20)->subSecond()->toDateTimeString()]);
        $this->assertDatabaseHas('paquetes_contrato', ['id' => 5, 'updated_at' => now()->subDays(5)->toDateTimeString()]);
        $eventId = DB::table('eventos')->where('nombre_evento', AutomaticCancellationEvent::INACTIVITY)->value('id');
        $this->assertNotNull($eventId);
        $this->assertDatabaseHas('eventos_contrato', [
            'codigo' => 'CONT-1',
            'evento_id' => $eventId,
            'user_id' => 7,
            'created_at' => now()->toDateTimeString(),
        ]);
        $this->assertDatabaseCount('eventos_contrato', 1);
        $this->artisan('contracts:cancel-expired-pickups')
            ->expectsOutput('Envios cancelados automaticamente: 0')->assertSuccessful();
        $this->assertDatabaseCount('eventos_contrato', 1);
        $this->assertDatabaseCount('eventos', 1);
    }

    public function test_missing_cancelled_state_leaves_requests_untouched(): void
    {
        DB::table('estados')->where('id', 29)->delete();
        DB::table('paquetes_contrato')->insert(['id' => 1, 'codigo' => 'CONT-1', 'estados_id' => 4, 'created_at' => now()->subDays(21)]);
        $this->artisan('contracts:cancel-expired-pickups')->assertFailed();
        $this->assertDatabaseHas('paquetes_contrato', ['id' => 1, 'estados_id' => 4]);
        $this->assertDatabaseCount('eventos_contrato', 0);
        $this->assertDatabaseCount('eventos', 0);
    }

    public function test_dry_run_counts_expired_requests_without_cancelling(): void
    {
        DB::table('paquetes_contrato')->insert(['id' => 1, 'codigo' => 'CONT-1', 'estados_id' => 4, 'created_at' => now()->subDays(21)]);
        $this->artisan('contracts:cancel-expired-pickups --dry-run')
            ->expectsOutput('Envios pendientes de cancelacion: 1')->assertSuccessful();
        $this->assertDatabaseHas('paquetes_contrato', ['id' => 1, 'estados_id' => 4]);
        $this->assertDatabaseCount('eventos_contrato', 0);
        $this->assertDatabaseCount('eventos', 0);
    }

    public function test_cancellation_and_event_catalog_are_rolled_back_if_tracking_cannot_be_saved(): void
    {
        DB::table('paquetes_contrato')->insert([
            ['id' => 1, 'codigo' => 'CONT-1', 'estados_id' => 4, 'created_at' => now()->subDays(21)],
            ['id' => 2, 'codigo' => 'CONT-2', 'estados_id' => 4, 'created_at' => now()->subDays(22)],
        ]);
        Schema::drop('eventos_contrato');

        try {
            $this->artisan('contracts:cancel-expired-pickups')->run();
            $this->fail('La cancelacion debe fallar si no puede guardar su evento.');
        } catch (QueryException) {
            $this->assertDatabaseHas('paquetes_contrato', ['id' => 1, 'estados_id' => 4]);
            $this->assertDatabaseHas('paquetes_contrato', ['id' => 2, 'estados_id' => 4]);
            $this->assertDatabaseCount('eventos', 0);
        }
    }

    public function test_all_batches_are_cancelled_and_existing_tracking_history_is_preserved(): void
    {
        DB::table('eventos')->insert([
            ['id' => 50, 'nombre_evento' => 'Paquete registrado.'],
            ['id' => 99, 'nombre_evento' => AutomaticCancellationEvent::INACTIVITY],
        ]);
        DB::table('eventos_contrato')->insert([
            'codigo' => 'CONT-1', 'evento_id' => 50, 'user_id' => 7, 'created_at' => now()->subDays(21),
        ]);
        DB::table('paquetes_contrato')->insert(array_map(fn (int $id): array => [
            'id' => $id,
            'codigo' => 'CONT-'.$id,
            'estados_id' => 4,
            'created_at' => now()->subDays(21),
        ], range(1, 201)));

        $this->artisan('contracts:cancel-expired-pickups')
            ->expectsOutput('Envios cancelados automaticamente: 201')->assertSuccessful();

        $this->assertSame(201, DB::table('paquetes_contrato')->where('estados_id', 29)->count());
        $this->assertSame(201, DB::table('eventos_contrato')->where('evento_id', 99)->count());
        $this->assertDatabaseHas('eventos_contrato', ['codigo' => 'CONT-201', 'evento_id' => 99]);
        $this->assertDatabaseHas('eventos_contrato', ['codigo' => 'CONT-1', 'evento_id' => 50]);
        $this->assertDatabaseCount('eventos', 2);
        $this->assertDatabaseCount('eventos_contrato', 202);
    }

    public function test_events_screen_preserves_automatic_reason_for_company_packages(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('ciudad')->nullable();
        });
        Schema::table('paquetes_contrato', function (Blueprint $table): void {
            $table->string('origen')->nullable();
            $table->string('provincia_origen')->nullable();
            $table->string('destino')->nullable();
            $table->string('provincia')->nullable();
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('model_id');
            $table->string('model_type');
        });
        DB::table('roles')->insert(['id' => 1, 'name' => 'empresa']);
        DB::table('model_has_roles')->insert([
            'role_id' => 1, 'model_id' => 7, 'model_type' => (new User)->getMorphClass(),
        ]);
        DB::table('paquetes_contrato')->insert([
            'codigo' => 'CONT-1', 'estados_id' => 4, 'created_at' => now()->subDays(21),
        ]);
        $this->artisan('contracts:cancel-expired-pickups')->assertSuccessful();
        DB::table('eventos')->insert(['id' => 4470, 'nombre_evento' => EncargadoEvent::CANCELADO]);
        DB::table('eventos_contrato')->insert([
            'codigo' => 'MANUAL-1', 'evento_id' => 4470, 'user_id' => 7, 'created_at' => now(),
        ]);

        Livewire::test(EventosTabla::class, ['tipo' => 'contrato'])
            ->assertSee(AutomaticCancellationEvent::INACTIVITY)
            ->assertSee('Sistema')
            ->assertViewHas('registros', function ($registros): bool {
                $automatic = $registros->firstWhere('codigo', 'CONT-1');
                $manual = $registros->firstWhere('codigo', 'MANUAL-1');

                return $automatic->evento_nombre_mostrado === AutomaticCancellationEvent::INACTIVITY
                    && ! $automatic->es_cancelacion_empresa
                    && $automatic->usuario_nombre === 'Sistema'
                    && $manual->es_cancelacion_empresa;
            });
    }
}
