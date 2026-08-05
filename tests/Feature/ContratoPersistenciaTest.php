<?php

namespace Tests\Feature;

use App\Http\Controllers\RecojoController;
use App\Models\Recojo;
use App\Models\User;
use App\Services\ContratoCodigoService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class ContratoPersistenciaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('paquetes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('empresa_id');
            $table->string('codigo');
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('paquetes_contrato');

        parent::tearDown();
    }

    public function test_confirma_el_paquete_consultando_la_base_de_datos_de_escritura(): void
    {
        $user = $this->userConId(25);
        $contrato = Recojo::query()->create([
            'user_id' => 25,
            'empresa_id' => 8,
            'codigo' => 'C0008A00001BO',
        ]);

        $confirmado = $this->controller()->confirmarPersistencia($contrato, $user);

        $this->assertSame($contrato->id, $confirmado->id);
        $this->assertSame('C0008A00001BO', $confirmado->codigo);
    }

    public function test_rechaza_la_guia_si_el_paquete_ya_no_existe_en_la_base_de_datos(): void
    {
        $user = $this->userConId(25);
        $contrato = Recojo::query()->create([
            'user_id' => 25,
            'empresa_id' => 8,
            'codigo' => 'C0008A00002BO',
        ]);

        Recojo::query()->whereKey($contrato->id)->delete();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('El paquete no quedo guardado en la base de datos.');

        $this->controller()->confirmarPersistencia($contrato, $user);
    }

    private function userConId(int $id): User
    {
        $user = new User;
        $user->id = $id;

        return $user;
    }

    private function controller(): RecojoController
    {
        return new class(app(ContratoCodigoService::class)) extends RecojoController
        {
            public function confirmarPersistencia(Recojo $contrato, User $user): Recojo
            {
                return $this->confirmarContratoPersistido($contrato, $user);
            }
        };
    }
}
