<?php

namespace Tests\Feature;

use App\Models\PaqueteCerti;
use App\Models\PaqueteEms;
use App\Models\PaqueteOrdi;
use App\Models\Recojo;
use App\Services\PackageCancellationService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PackageCancellationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('eventos', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_evento');
            $table->timestamps();
        });

        foreach ([
            'paquetes_ems' => 'estado_id',
            'paquetes_ordi' => 'fk_estado',
            'paquetes_certi' => 'fk_estado',
            'paquetes_contrato' => 'estados_id',
        ] as $tableName => $stateColumn) {
            Schema::create($tableName, function (Blueprint $table) use ($stateColumn): void {
                $table->id();
                $table->unsignedBigInteger($stateColumn)->nullable();
                $table->timestamps();
            });
        }

        Schema::create('eventos_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id');
            $table->text('detalle_evento')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('eventos_contrato');
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('paquetes_certi');
        Schema::dropIfExists('paquetes_ordi');
        Schema::dropIfExists('paquetes_ems');
        Schema::dropIfExists('eventos');
        Schema::dropIfExists('users');
        Schema::dropIfExists('estados');

        parent::tearDown();
    }

    public function test_all_package_types_are_preserved_and_moved_to_cancelled_state(): void
    {
        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'ACTIVO'],
            ['id' => 29, 'nombre_estado' => 'CANCELADO'],
        ]);

        $packages = [
            [new PaqueteEms, 'paquetes_ems', 'estado_id'],
            [new PaqueteOrdi, 'paquetes_ordi', 'fk_estado'],
            [new PaqueteCerti, 'paquetes_certi', 'fk_estado'],
            [new Recojo, 'paquetes_contrato', 'estados_id'],
        ];

        $service = app(PackageCancellationService::class);

        foreach ($packages as [$model, $tableName, $stateColumn]) {
            $id = DB::table($tableName)->insertGetId([$stateColumn => 1]);
            $package = $model->newQuery()->findOrFail($id);

            $this->assertTrue($service->cancel($package, $stateColumn));
            $this->assertDatabaseHas($tableName, [
                'id' => $id,
                $stateColumn => 29,
            ]);
        }
    }

    public function test_package_is_untouched_when_cancelled_state_does_not_exist(): void
    {
        DB::table('estados')->insert(['id' => 1, 'nombre_estado' => 'ACTIVO']);
        $id = DB::table('paquetes_ordi')->insertGetId(['fk_estado' => 1]);
        $package = PaqueteOrdi::query()->findOrFail($id);

        $this->assertFalse(app(PackageCancellationService::class)->cancel($package, 'fk_estado'));
        $this->assertDatabaseHas('paquetes_ordi', ['id' => $id, 'fk_estado' => 1]);
    }

    public function test_contract_cancellation_records_one_tracking_event(): void
    {
        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'SOLICITUD'],
            ['id' => 29, 'nombre_estado' => 'CANCELADO'],
        ]);
        DB::table('users')->insert(['id' => 7, 'name' => 'Empresa']);
        DB::table('eventos')->insert([
            'id' => 4470,
            'nombre_evento' => 'Envio cancelado desde encargado.',
        ]);

        $id = DB::table('paquetes_contrato')->insertGetId(['estados_id' => 1]);
        $package = Recojo::query()->findOrFail($id);
        $service = app(PackageCancellationService::class);

        $this->assertTrue($service->cancelAndRecordEvent(
            $package,
            'estados_id',
            'eventos_contrato',
            'CONTRATO-001',
            4470,
            7,
            'Envio cancelado por Empresa.'
        ));

        // Repetir la solicitud no debe duplicar el evento de cancelacion.
        $this->assertTrue($service->cancelAndRecordEvent(
            $package,
            'estados_id',
            'eventos_contrato',
            'CONTRATO-001',
            4470,
            7,
            'Envio cancelado por Empresa.'
        ));

        $this->assertDatabaseHas('paquetes_contrato', [
            'id' => $id,
            'estados_id' => 29,
        ]);
        $this->assertDatabaseHas('eventos_contrato', [
            'codigo' => 'CONTRATO-001',
            'evento_id' => 4470,
            'user_id' => 7,
            'detalle_evento' => 'Envio cancelado por Empresa.',
        ]);
        $this->assertSame(1, DB::table('eventos_contrato')->count());
    }
}
