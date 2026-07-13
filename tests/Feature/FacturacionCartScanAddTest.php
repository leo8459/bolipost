<?php

namespace Tests\Feature;

use App\Exceptions\FacturacionScanConflictException;
use App\Models\PaqueteEms;
use App\Models\PaqueteInt;
use App\Models\SolicitudCliente;
use App\Models\User;
use App\Services\FacturacionCartService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class FacturacionCartScanAddTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_ordi', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_certi', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_ems', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('cod_especial')->nullable();
            $table->timestamps();
        });

        Schema::create('paquetes_int', function (Blueprint $table) {
            $table->id();
            $table->string('codigo')->nullable();
            $table->string('cod_especial')->nullable();
            $table->timestamps();
        });

        Schema::create('solicitud_clientes', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_solicitud')->nullable();
            $table->string('barcode')->nullable();
            $table->string('cod_especial')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');
        Schema::dropIfExists('paquetes_ordi');
        Schema::dropIfExists('paquetes_certi');
        Schema::dropIfExists('paquetes_ems');
        Schema::dropIfExists('paquetes_int');
        Schema::dropIfExists('solicitud_clientes');

        Mockery::close();

        parent::tearDown();
    }

    public function test_scan_add_resolves_paquete_ems_by_codigo(): void
    {
        $paquete = PaqueteEms::query()->create([
            'codigo' => 'EN000005705LPZ',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('feature.dashboard.facturacion')
            ->andReturn(true);

        $service = Mockery::mock(FacturacionCartService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('addPaqueteEms')
            ->once()
            ->withArgs(function ($receivedUser, $receivedPaquete) use ($user, $paquete) {
                return $receivedUser === $user && $receivedPaquete->id === $paquete->id;
            })
            ->andReturn((object) ['id' => 99]);

        $resultado = $service->addScannedItemByCode($user, 'EN000005705LPZ');

        $this->assertSame('ems', $resultado['item']['type']);
        $this->assertSame('Paquete EMS', $resultado['item']['label']);
        $this->assertSame('EN000005705LPZ', $resultado['item']['code']);
        $this->assertSame(99, $resultado['cart']->id);
    }

    public function test_scan_add_resolves_solicitud_ems_by_barcode(): void
    {
        $solicitud = SolicitudCliente::query()->create([
            'codigo_solicitud' => 'SL00000016LP',
            'barcode' => 'SL00000016LP',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('feature.dashboard.facturacion')
            ->andReturn(true);

        $service = Mockery::mock(FacturacionCartService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('addSolicitudEms')
            ->once()
            ->withArgs(function ($receivedUser, $receivedSolicitud) use ($user, $solicitud) {
                return $receivedUser === $user && $receivedSolicitud->id === $solicitud->id;
            })
            ->andReturn((object) ['id' => 123]);

        $resultado = $service->addScannedItemByCode($user, 'SL00000016LP');

        $this->assertSame('solicitud_ems', $resultado['item']['type']);
        $this->assertSame('Solicitud EMS', $resultado['item']['label']);
        $this->assertSame('SL00000016LP', $resultado['item']['code']);
        $this->assertSame(123, $resultado['cart']->id);
    }

    public function test_scan_add_resolves_paquete_int_by_codigo(): void
    {
        $paquete = PaqueteInt::query()->create([
            'codigo' => 'INT-0001',
            'cod_especial' => 'INTS-0001',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('feature.dashboard.facturacion')
            ->andReturn(true);

        $service = Mockery::mock(FacturacionCartService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('addPaqueteInt')
            ->once()
            ->withArgs(function ($receivedUser, $receivedPaquete) use ($user, $paquete) {
                return $receivedUser === $user && $receivedPaquete->id === $paquete->id;
            })
            ->andReturn((object) ['id' => 321]);

        $resultado = $service->addScannedItemByCode($user, 'INT-0001');

        $this->assertSame('interno', $resultado['item']['type']);
        $this->assertSame('Paquete Interno', $resultado['item']['label']);
        $this->assertSame('INT-0001', $resultado['item']['code']);
        $this->assertSame(321, $resultado['cart']->id);
    }

    public function test_scan_add_throws_conflict_with_choices_when_code_exists_in_multiple_modules(): void
    {
        PaqueteEms::query()->create([
            'codigo' => 'DUP-0001',
        ]);

        PaqueteInt::query()->create([
            'codigo' => 'DUP-0001',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('feature.dashboard.facturacion')
            ->andReturn(true);

        $service = app(FacturacionCartService::class);

        try {
            $service->addScannedItemByCode($user, 'DUP-0001');
            $this->fail('Expected FacturacionScanConflictException was not thrown.');
        } catch (FacturacionScanConflictException $e) {
            $this->assertCount(2, $e->matches());
            $this->assertSame(['ems', 'interno'], collect($e->matches())->pluck('type')->sort()->values()->all());
        }
    }

    public function test_scan_add_resolves_selected_match_when_user_chooses_one_option(): void
    {
        $ems = PaqueteEms::query()->create([
            'codigo' => 'DUP-0002',
        ]);

        PaqueteInt::query()->create([
            'codigo' => 'DUP-0002',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('feature.dashboard.facturacion')
            ->andReturn(true);

        $service = Mockery::mock(FacturacionCartService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('addPaqueteEms')
            ->once()
            ->withArgs(function ($receivedUser, $receivedPaquete) use ($user, $ems) {
                return $receivedUser === $user && $receivedPaquete->id === $ems->id;
            })
            ->andReturn((object) ['id' => 456]);

        $resultado = $service->addScannedItemByCode($user, 'DUP-0002', 'ems', $ems->id);

        $this->assertSame('ems', $resultado['item']['type']);
        $this->assertSame(456, $resultado['cart']->id);
    }

    public function test_scan_add_opens_conflict_modal_when_duplicate_exists_in_secondary_field(): void
    {
        PaqueteInt::query()->create([
            'codigo' => 'MAIN-0001',
            'cod_especial' => 'SHARED-0001',
        ]);

        PaqueteEms::query()->create([
            'codigo' => 'OTHER-0001',
            'cod_especial' => 'SHARED-0001',
        ]);

        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')
            ->with('feature.dashboard.facturacion')
            ->andReturn(true);

        $service = app(FacturacionCartService::class);

        try {
            $service->addScannedItemByCode($user, 'SHARED-0001');
            $this->fail('Expected FacturacionScanConflictException was not thrown.');
        } catch (FacturacionScanConflictException $e) {
            $this->assertCount(2, $e->matches());
            $this->assertSame(['ems', 'interno'], collect($e->matches())->pluck('type')->sort()->values()->all());
        }
    }
}
