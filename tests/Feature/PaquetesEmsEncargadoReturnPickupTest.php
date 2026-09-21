<?php

namespace Tests\Feature;

use App\Http\Controllers\PaquetesEmsController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use Tests\TestCase;

class PaquetesEmsEncargadoReturnPickupTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('estados', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_estado');
        });
        DB::table('estados')->insert([
            ['id' => 1, 'nombre_estado' => 'ALMACEN'],
            ['id' => 2, 'nombre_estado' => 'RECIBIDO'],
        ]);
        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('codigo');
            $table->integer('estados_id');
            $table->decimal('peso', 8, 3);
            $table->dateTime('fecha_recojo')->nullable();
            $table->timestamps();
        });
    }

    public function test_both_returns_without_pickup_date_preserve_state_and_weight(): void
    {
        foreach (['origen', 'destino'] as $destination) {
            foreach ([0, 0.12] as $weight) {
                DB::table('paquetes_contrato')->updateOrInsert(['id' => 1], [
                    'codigo' => 'C0007A15474BO',
                    'estados_id' => 3,
                    'peso' => $weight,
                    'fecha_recojo' => null,
                ]);

                try {
                    $this->returnPackage($destination);
                    $this->fail('La devolucion sin fecha de recojo debe bloquearse.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('fecha_recojo', $exception->errors());
                }

                $record = DB::table('paquetes_contrato')->find(1);
                $this->assertSame(3, $record->estados_id);
                $this->assertEquals($weight, $record->peso);
            }
        }
    }

    public function test_with_pickup_date_return_reaches_existing_weight_validation(): void
    {
        DB::table('paquetes_contrato')->insert([
            'id' => 1, 'codigo' => 'C0007A15474BO', 'estados_id' => 3,
            'peso' => 0, 'fecha_recojo' => '2026-09-17 10:00:00',
        ]);

        foreach (['origen', 'destino'] as $destination) {
            try {
                $this->returnPackage($destination, null);
                $this->fail('Debe solicitar el peso del contrato.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('peso', $exception->errors());
                $this->assertArrayNotHasKey('fecha_recojo', $exception->errors());
            }
        }
    }

    private function returnPackage(string $destination, ?string $weight = '0.500'): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 10;
        $user->name = 'Encargado';
        $user->shouldReceive('can')->andReturn(true);
        $request = Request::create('/paquetes-ems/encargado/devolver-envio', 'POST', [
            'id' => 1, 'servicio' => 'CONTRATO',
            'destino_accion' => $destination, 'peso' => $weight,
        ]);
        $request->setUserResolver(fn () => $user);

        app(PaquetesEmsController::class)->devolverEnvioEncargado($request);
    }
}
