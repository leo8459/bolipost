<?php

namespace Tests\Feature;

use App\Http\Controllers\CarterosController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class CarterosDeliveryDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('eventos_contrato');
        Schema::dropIfExists('paquetes_contrato');

        Schema::create('paquetes_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('estados_id');
            $table->string('imagen')->nullable();
            $table->timestamps();
        });

        Schema::create('eventos_contrato', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('eventos_contrato');
        Schema::dropIfExists('paquetes_contrato');

        parent::tearDown();
    }

    public function test_selected_delivery_datetime_is_used_for_package_and_event(): void
    {
        DB::table('paquetes_contrato')->insert([
            'id' => 20051,
            'codigo' => 'CONTRATO-20051',
            'estados_id' => 1,
            'imagen' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $controller = new CarterosController();
        $deliveryDate = Carbon::parse('2026-08-15 14:35:00');

        $this->invokePrivateMethod(
            $controller,
            'updatePackageState',
            ['CONTRATO', 20051, 9, $deliveryDate]
        );
        $this->invokePrivateMethod(
            $controller,
            'updatePackageImage',
            ['CONTRATO', 20051, 'entregas/foto.jpg', $deliveryDate]
        );
        $this->invokePrivateMethod(
            $controller,
            'insertEventoPorPaquete',
            ['CONTRATO', 20051, 316, 7, $deliveryDate]
        );

        $package = DB::table('paquetes_contrato')->where('id', 20051)->first();
        $event = DB::table('eventos_contrato')->where('codigo', 'CONTRATO-20051')->first();

        $this->assertSame(9, (int) $package->estados_id);
        $this->assertSame('entregas/foto.jpg', $package->imagen);
        $this->assertSame('2026-08-15 14:35:00', Carbon::parse($package->updated_at)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 14:35:00', Carbon::parse($event->created_at)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-15 14:35:00', Carbon::parse($event->updated_at)->format('Y-m-d H:i:s'));
    }

    public function test_delivery_datetime_cannot_be_before_latest_package_event(): void
    {
        DB::table('paquetes_contrato')->insert([
            'id' => 20051,
            'codigo' => 'CONTRATO-20051',
            'estados_id' => 1,
            'imagen' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('eventos_contrato')->insert([
            'codigo' => 'CONTRATO-20051',
            'evento_id' => 184,
            'user_id' => 7,
            'created_at' => '2026-08-15 14:35:00',
            'updated_at' => '2026-08-15 14:35:00',
        ]);

        $controller = new CarterosController();

        $this->invokePrivateMethod(
            $controller,
            'validateDeliveryDateAgainstLastEvent',
            ['CONTRATO', 20051, Carbon::parse('2026-08-15 14:35:00')]
        );

        try {
            $this->invokePrivateMethod(
                $controller,
                'validateDeliveryDateAgainstLastEvent',
                ['CONTRATO', 20051, Carbon::parse('2026-08-15 14:34:00')]
            );
            $this->fail('Se permitio una fecha de entrega anterior al ultimo evento.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'La fecha de entrega no puede ser anterior al ultimo evento registrado (15/08/2026 14:35).',
                $exception->errors()['fecha_entrega'][0]
            );
        }
    }

    private function invokePrivateMethod(object $target, string $method, array $arguments): mixed
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($target, $arguments);
    }
}
