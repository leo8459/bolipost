<?php

namespace Tests\Feature;

use App\Models\Cliente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClienteRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_genera_codigo_cliente_correlativo_al_crear_clientes(): void
    {
        $primerCliente = Cliente::query()->create([
            'name' => 'Cliente Uno',
            'email' => 'cliente1@example.com',
            'password' => 'secret123',
            'provider' => 'local',
            'rol' => 'tiktokero',
        ]);

        $segundoCliente = Cliente::query()->create([
            'name' => 'Cliente Dos',
            'email' => 'cliente2@example.com',
            'password' => 'secret123',
            'provider' => 'local',
            'rol' => 'tiktokero',
        ]);

        $this->assertSame('COD000001', $primerCliente->codigo_cliente);
        $this->assertSame('COD000002', $segundoCliente->codigo_cliente);
    }

    public function test_cliente_incompleto_es_redirigido_a_completar_perfil(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Demo',
            'email' => 'cliente3@example.com',
            'password' => 'secret123',
            'provider' => 'local',
            'rol' => 'tiktokero',
        ]);

        $response = $this
            ->actingAs($cliente, 'cliente')
            ->get(route('clientes.dashboard'));

        $response->assertRedirect(route('clientes.profile.complete'));
    }

    public function test_cliente_puede_completar_su_perfil(): void
    {
        $cliente = Cliente::query()->create([
            'name' => 'Cliente Demo',
            'email' => 'cliente4@example.com',
            'password' => 'secret123',
            'provider' => 'local',
            'rol' => 'tiktokero',
        ]);

        $response = $this
            ->actingAs($cliente, 'cliente')
            ->post(route('clientes.profile.complete.store'), [
                'tipodocumentoidentidad' => '1',
                'complemento' => 'A',
                'numero_carnet' => '1234567',
                'razon_social' => 'Cliente Demo SRL',
                'telefono' => '70000000',
                'direccion' => 'Zona Central',
            ]);

        $response->assertRedirect(route('clientes.dashboard'));
        $this->assertDatabaseHas('clientes', [
            'id' => $cliente->id,
            'tipodocumentoidentidad' => '1',
            'complemento' => 'A',
            'numero_carnet' => '1234567',
            'razon_social' => 'Cliente Demo SRL',
            'telefono' => '70000000',
            'direccion' => 'Zona Central',
        ]);
    }
}
