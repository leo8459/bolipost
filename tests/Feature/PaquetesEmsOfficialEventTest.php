<?php

namespace Tests\Feature;

use App\Livewire\PaquetesEms;
use App\Models\PaqueteEms;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PaquetesEmsOfficialEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['paquetes_ems', 'paquetes_ems_formulario'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name): void {
                $table->id();
                foreach ([
                    'origen', 'tipo_correspondencia', 'servicio_especial', 'contenido',
                    'codigo', 'nombre_remitente', 'nombre_envia', 'carnet',
                    'telefono_remitente', 'nombre_destinatario', 'telefono_destinatario',
                    'correo_electronico', 'direccion', 'referencia', 'ciudad',
                ] as $column) {
                    $table->string($column)->nullable();
                }
                $table->integer('cantidad');
                $table->decimal('peso', 10, 3);
                $table->decimal('precio', 10, 2);
                $table->unsignedBigInteger('tarifario_id')->nullable();
                if ($name === 'paquetes_ems') {
                    $table->text('observacion')->nullable();
                    $table->unsignedBigInteger('tarifario_version_id')->nullable();
                    $table->text('tarifario_snapshot')->nullable();
                    $table->unsignedBigInteger('estado_id');
                    $table->unsignedBigInteger('user_id');
                } else {
                    $table->unsignedBigInteger('paquete_ems_id')->unique();
                    $table->unsignedBigInteger('servicio_id')->nullable();
                    $table->unsignedBigInteger('destino_id')->nullable();
                }
                $table->timestamps();
            });
        }
        Schema::create('estados', function (Blueprint $table): void {
            $table->id();
            $table->string('nombre_estado');
        });
        Schema::create('eventos_ems', function (Blueprint $table): void {
            $table->id();
            $table->string('codigo');
            $table->unsignedBigInteger('evento_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamps();
        });
    }

    public function test_creating_an_official_in_warehouse_records_receipt_and_the_authenticated_user(): void
    {
        $this->freezeTime();

        $user = new class extends User
        {
            public function can($abilities, $arguments = [])
            {
                return $abilities === 'feature.paquetes-ems.almacen.create';
            }
        };
        $user->forceFill(['id' => 42, 'ciudad' => 'LA PAZ']);
        $this->actingAs($user);
        DB::table('estados')->insert(['id' => 7, 'nombre_estado' => 'ALMACEN']);

        $component = new PaquetesEms;
        $component->isAlmacenEms = true;
        $component->isAdmision = false;
        $component->isCreateEms = false;
        $component->oficialDestino = 'COCHABAMBA';
        $component->oficialPeso = '0.500';
        $component->oficialNombreRemitente = 'Unidad de Administracion';
        $component->oficialNombreDestinatario = 'Juan Perez';
        $component->oficialDireccionDestinatario = 'Av. Principal 123';
        $component->oficialObservacion = 'Documentacion oficial';

        $component->guardarEnvioOficial();

        $paquete = PaqueteEms::query()->sole();
        $this->assertSame('OFICIAL', $paquete->tipo_correspondencia);
        $this->assertSame(7, $paquete->estado_id);
        $this->assertSame(42, $paquete->user_id);
        $this->assertDatabaseCount('paquetes_ems_formulario', 1);
        $this->assertDatabaseCount('eventos_ems', 1);
        $this->assertDatabaseHas('eventos_ems', [
            'codigo' => $paquete->codigo,
            'evento_id' => 295,
            'user_id' => 42,
            'created_at' => now()->toDateTimeString(),
        ]);
    }
}
