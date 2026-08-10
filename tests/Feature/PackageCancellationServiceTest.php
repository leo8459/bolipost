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
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('paquetes_certi');
        Schema::dropIfExists('paquetes_ordi');
        Schema::dropIfExists('paquetes_ems');
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
}
