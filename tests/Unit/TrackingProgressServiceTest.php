<?php

namespace Tests\Unit;

use App\Services\TrackingProgressService;
use Tests\TestCase;

class TrackingProgressServiceTest extends TestCase
{
    public function test_upu_events_advance_to_the_highest_postal_stage(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 1, 'nombre_evento' => 'Recibir envio del cliente'],
            (object) ['codigo_evento' => 8, 'nombre_evento' => 'Insertar envio en saca'],
            (object) ['codigo_evento' => 35, 'nombre_evento' => 'Enviar envio a ubicacion nacional'],
        ], 'EMS');

        $this->assertSame(['Admision', 'Despacho', 'Expedicion', 'Ventanilla', 'Entregado'], $progress['steps']);
        $this->assertSame(2, $progress['current_index']);
        $this->assertSame('En transito', $progress['status']);
    }

    public function test_national_delivery_event_marks_delivered(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['evento_id' => 295, 'nombre_evento' => 'Paquete recibido del cliente.'],
            (object) ['evento_id' => 4477, 'nombre_evento' => 'Paquete asignado a cartero para entrega fisica.'],
            (object) ['evento_id' => 316, 'nombre_evento' => 'Paquete entregado exitosamente.'],
        ], 'CERTI');

        $this->assertSame(['Clasificacion', 'Despacho', 'Expedicion', 'Ventanilla', 'Cartero', 'Entregado'], $progress['steps']);
        $this->assertSame(5, $progress['current_index']);
        $this->assertSame('Entregado', $progress['status']);
    }

    public function test_incident_keeps_the_highest_stage_without_marking_delivered(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 35, 'nombre_evento' => 'Enviar envio a ubicacion nacional (entrada)'],
            (object) ['codigo_evento' => 70, 'nombre_evento' => 'Retener envio en oficina de cambio (entrada)'],
        ], 'EMS');

        $this->assertSame(2, $progress['current_index']);
        $this->assertSame('En transito con incidencia', $progress['status']);
    }

    public function test_national_delivery_express_events_cover_each_initial_stage(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['evento_id' => 2239, 'nombre_evento' => 'Delivery Express registrado.'],
            (object) ['evento_id' => 2240, 'nombre_evento' => 'Delivery Express recibido en almacen.'],
            (object) ['evento_id' => 2241, 'nombre_evento' => 'Delivery Express enviado en saca interna.'],
        ], 'EMS');

        $this->assertSame(2, $progress['current_index']);
        $this->assertSame('Expedicion', $progress['steps'][$progress['current_index']]);
    }

    public function test_national_cancellation_is_an_incident_without_advancing_the_progress(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['evento_id' => 2239, 'nombre_evento' => 'Delivery Express registrado.'],
            (object) ['evento_id' => 4470, 'nombre_evento' => 'Envio cancelado desde encargado.'],
        ], 'EMS');

        $this->assertSame(0, $progress['current_index']);
        $this->assertSame('En transito con incidencia', $progress['status']);
    }
}
