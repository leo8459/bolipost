<?php

namespace Tests\Feature;

use App\Mail\SolicitudClienteCreadaMail;
use App\Models\Cliente;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClienteSolicitudMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envia_correo_al_cliente_cuando_registra_una_solicitud(): void
    {
        Mail::fake();

        $user = User::factory()->create();

        $cliente = Cliente::query()->create([
            'user_id' => $user->id,
            'provider' => 'local',
            'name' => 'Cliente Demo',
            'email' => 'cliente@example.com',
            'password' => 'secret123',
            'rol' => 'tiktokero',
        ]);

        $destinoId = DB::table('destino')->insertGetId([
            'nombre_destino' => 'SANTA CRUZ',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $servicioExtraId = DB::table('servicio_extras')->insertGetId([
            'nombre' => 'serviciotiktokero',
            'descripcion' => 'Servicio tiktokero',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this
            ->actingAs($cliente, 'cliente')
            ->post(route('clientes.solicitudes.store'), [
                'servicio_extra_id' => $servicioExtraId,
                'origen' => 'LA PAZ',
                'tipo_correspondencia' => 'Sobre',
                'destino_id' => $destinoId,
                'cantidad' => 1,
                'contenido' => 'Documentos',
                'nombre_remitente' => 'Cliente Demo',
                'carnet' => '1234567',
                'telefono_remitente' => '70000000',
                'nombre_destinatario' => 'Destinatario Demo',
                'telefono_destinatario' => '71111111',
                'direccion_recojo' => 'Zona Central',
                'direccion_entrega' => 'Avenida Principal',
            ]);

        $response->assertRedirect(route('clientes.solicitudes.index'));
        $response->assertSessionHas('success');

        Mail::assertSent(SolicitudClienteCreadaMail::class, function (SolicitudClienteCreadaMail $mail) use ($cliente) {
            return $mail->hasTo($cliente->email)
                && str_starts_with($mail->solicitud->codigo_solicitud, 'SOL');
        });
    }
}
