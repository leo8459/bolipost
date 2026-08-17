<?php

namespace Tests\Feature;

use App\Http\Controllers\BitacoraController;
use App\Models\User;
use App\Services\BitacoraFacturaQrService;
use App\Support\BitacoraCn33Service;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class BitacoraPaquetesIntTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('bitacoras', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
        });

        foreach (['paquetes_ems', 'paquetes_contrato', 'paquetes_ordi'] as $tableName) {
            Schema::create($tableName, function (Blueprint $table) {
                $table->id();
                $table->string('cod_especial')->nullable();
                $table->decimal('peso', 10, 3)->nullable();
                $table->decimal('precio', 10, 2)->nullable();
            });
        }

        Schema::create('paquetes_certi', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->string('codigo')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
        });

        Schema::create('paquetes_int', function (Blueprint $table) {
            $table->id();
            $table->string('cod_especial')->nullable();
            $table->decimal('peso', 10, 3)->nullable();
            $table->decimal('precio', 10, 2)->nullable();
        });
    }

    protected function tearDown(): void
    {
        foreach (['paquetes_int', 'paquetes_certi', 'paquetes_ordi', 'paquetes_contrato', 'paquetes_ems', 'bitacoras'] as $tableName) {
            Schema::dropIfExists($tableName);
        }

        parent::tearDown();
    }

    public function test_permite_registrar_un_cn33_que_solo_tiene_paquetes_int(): void
    {
        Storage::fake('public');

        DB::table('paquetes_int')->insert([
            'cod_especial' => 'CN-33-INT-001',
            'peso' => 3.250,
            'precio' => 42.50,
        ]);

        $user = new User();
        $user->id = 7;
        Auth::setUser($user);

        $facturaQrService = Mockery::mock(BitacoraFacturaQrService::class);
        $facturaQrService
            ->shouldReceive('extractFromUploadedFile')
            ->once()
            ->andReturn(['success' => false]);

        $controller = new BitacoraController(
            app(BitacoraCn33Service::class),
            $facturaQrService
        );

        $request = Request::create('/bitacoras', 'POST', [
            'cod_especial' => ' cn-33-int-001 ',
            'transportadora' => 'transportes prueba',
            'provincia' => 'oruro',
            'factura' => 'F-001',
            'precio_total' => '42.50',
            'peso' => '3.250',
        ], [], [
            'imagen_factura' => UploadedFile::fake()->create('factura.pdf', 10, 'application/pdf'),
        ]);

        $method = new ReflectionMethod($controller, 'validateStoreData');
        $payload = $method->invoke($controller, $request);

        $this->assertSame('CN-33-INT-001', $payload['cod_especial']);
        $this->assertSame('TRANSPORTES PRUEBA', $payload['transportadora']);
        $this->assertSame(7, $payload['user_id']);
        $this->assertNotEmpty($payload['imagen_factura']);
    }
}
