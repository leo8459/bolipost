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

    public function test_delivery_after_customs_marks_the_final_step_complete(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['evento_id' => 316, 'nombre_evento' => 'Paquete entregado exitosamente.'],
            (object) ['codigo_evento' => 34, 'nombre_evento' => 'Registrar informacion de aduanas sobre el envio (entrada)'],
            (object) ['codigo_evento' => 31, 'nombre_evento' => 'Send item to customs (Inb)'],
        ], 'ORDI');

        $this->assertSame(4, $progress['current_index']);
        $this->assertSame('Entregado', $progress['steps'][$progress['current_index']]);
        $this->assertSame('Entregado', $progress['status']);
        $this->assertFalse($progress['is_customs']);
    }

    public function test_incident_keeps_the_highest_stage_without_marking_delivered(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 35, 'nombre_evento' => 'Enviar envio a ubicacion nacional (entrada)'],
            (object) ['codigo_evento' => 70, 'nombre_evento' => 'Retener envio en oficina de cambio (entrada)'],
        ], 'EMS');

        $this->assertSame(2, $progress['current_index']);
        $this->assertSame('En transito con incidencia', $progress['status']);
        $this->assertFalse($progress['is_held']);
        $this->assertFalse($progress['is_held_by_customs']);
    }

    public function test_latest_explicit_exchange_retention_is_shown_as_held(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 70, 'nombre_evento' => 'Retener envio en oficina de cambio (entrada)'],
            (object) ['codigo_evento' => 35, 'nombre_evento' => 'Enviar envio a ubicacion nacional (entrada)'],
        ], 'EMS');

        $this->assertSame(2, $progress['current_index']);
        $this->assertSame('Retenido en oficina de cambio', $progress['status']);
        $this->assertTrue($progress['is_held']);
        $this->assertFalse($progress['is_held_by_customs']);
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

    public function test_national_cancellation_replaces_dispatch_with_a_terminal_red_step(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['evento_id' => 4470, 'nombre_evento' => 'Envio cancelado desde encargado.'],
            (object) ['evento_id' => 2239, 'nombre_evento' => 'Delivery Express registrado.'],
        ], 'EMS');

        $this->assertSame('Cancelado', $progress['steps'][1]);
        $this->assertSame(1, $progress['current_index']);
        $this->assertTrue($progress['is_cancelled']);
        $this->assertSame('Envio cancelado', $progress['status']);
    }

    public function test_latest_customs_event_adds_a_blue_customs_step_after_expedition(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 34, 'nombre_evento' => 'Registrar informacion de aduanas sobre el envio'],
            (object) ['codigo_evento' => 30, 'nombre_evento' => 'Paquete recibido en oficina de transito'],
        ], 'EMS');

        $this->assertSame(['Admision', 'Despacho', 'Expedicion', 'Ventanilla = Aduana', 'Entregado'], $progress['steps']);
        $this->assertSame(3, $progress['current_index']);
        $this->assertTrue($progress['is_customs']);
        $this->assertSame('En Aduana', $progress['status']);
    }

    public function test_sending_item_to_customs_does_not_itself_mean_an_incident(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 31, 'nombre_evento' => 'Send item to customs (Inb)'],
        ], 'EMS');

        $this->assertSame('Ventanilla = Aduana', $progress['steps'][$progress['current_index']]);
        $this->assertTrue($progress['is_customs']);
        $this->assertFalse($progress['has_incident']);
        $this->assertFalse($progress['is_held']);
    }

    public function test_customs_information_at_destination_makes_combined_step_ready_for_pickup(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) [
                'codigo_evento' => 34,
                'nombre_evento' => 'Registrar informacion de aduanas sobre el envio (entrada)',
                'office' => 'BOORUB - ORURO',
                'ciudad_destino' => 'Oruro',
            ],
            (object) [
                'codigo_evento' => 31,
                'nombre_evento' => 'Send item to customs (Inb)',
                'office' => 'BOSRZA - SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                'ciudad_destino' => 'Oruro',
            ],
        ], 'ORDI');

        $this->assertSame('Ventanilla = Aduana', $progress['steps'][$progress['current_index']]);
        $this->assertTrue($progress['is_pickup_available']);
        $this->assertTrue($progress['pickup_ready_at_destination_customs']);
        $this->assertFalse($progress['is_customs']);
        $this->assertSame('Listo para recoger', $progress['status']);
    }

    public function test_customs_event_in_transit_city_does_not_make_package_ready_for_destination_pickup(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) [
                'codigo_evento' => 31,
                'nombre_evento' => 'Send item to customs (Inb)',
                'office' => 'BOSRZA - SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                'ciudad_destino' => 'La Paz',
            ],
            (object) [
                'codigo_evento' => 30,
                'nombre_evento' => 'Receive item at inward office of exchange',
                'office' => 'BOSRZA - SANTA CRUZ DE LA SIERRA LC/AO-AVION',
                'ciudad_destino' => 'La Paz',
            ],
        ], 'CERTI');

        $this->assertSame('Ventanilla = Aduana', $progress['steps'][$progress['current_index']]);
        $this->assertFalse($progress['is_pickup_available']);
        $this->assertFalse($progress['pickup_ready_at_destination_customs']);
        $this->assertTrue($progress['is_customs']);
        $this->assertSame('En Aduana', $progress['status']);
    }

    public function test_customs_step_stays_completed_after_the_package_reaches_the_counter(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 32, 'nombre_evento' => 'Paquete listo para entregar en oficina'],
            (object) ['codigo_evento' => 34, 'nombre_evento' => 'Registrar informacion de aduanas sobre el envio'],
            (object) ['codigo_evento' => 30, 'nombre_evento' => 'Paquete recibido en oficina de transito'],
        ], 'EMS');

        $this->assertSame(['Admision', 'Despacho', 'Expedicion', 'Ventanilla = Aduana', 'Entregado'], $progress['steps']);
        $this->assertSame(3, $progress['current_index']);
        $this->assertFalse($progress['is_customs']);
        $this->assertTrue($progress['is_pickup_available']);
    }

    public function test_item_returned_from_customs_is_not_mistaken_for_return_to_sender(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 38, 'nombre_evento' => 'Return item from customs (Inb)'],
            (object) ['codigo_evento' => 34, 'nombre_evento' => 'Record item customs information (Inb)'],
            (object) ['codigo_evento' => 31, 'nombre_evento' => 'Send item to customs (Inb)'],
        ], 'EMS');

        $this->assertFalse($progress['is_returning']);
        $this->assertFalse($progress['is_customs']);
        $this->assertSame('En transito', $progress['status']);
        $this->assertSame('Ventanilla = Aduana', $progress['steps'][$progress['current_index']]);
    }

    public function test_customs_reason_65_requests_recipient_action_in_the_combined_flow(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) [
                'codigo_evento' => 34,
                'RETENTION_REASON_CD' => 65,
                'nombre_evento' => 'Record item customs information (Inb)',
            ],
            (object) ['codigo_evento' => 31, 'nombre_evento' => 'Send item to customs (Inb)'],
        ], 'EMS');

        $this->assertSame('Ventanilla = Aduana', $progress['steps'][$progress['current_index']]);
        $this->assertTrue($progress['customs_action_required']);
        $this->assertTrue($progress['customs_purchase_proof_required']);
        $this->assertTrue($progress['is_held_by_customs']);
        $this->assertFalse($progress['is_pickup_available']);
    }

    public function test_event_75_marks_the_combined_customs_counter_step_ready_for_pickup(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['codigo_evento' => 75, 'nombre_evento' => 'Receive item at collection point for pick-up (Inb)'],
            (object) ['codigo_evento' => 38, 'nombre_evento' => 'Return item from customs (Inb)'],
            (object) ['codigo_evento' => 31, 'nombre_evento' => 'Send item to customs (Inb)'],
        ], 'EMS');

        $this->assertSame('Ventanilla = Aduana', $progress['steps'][$progress['current_index']]);
        $this->assertTrue($progress['is_pickup_available']);
        $this->assertFalse($progress['customs_action_required']);
    }

    public function test_local_pickup_point_event_marks_ventanilla_available(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['evento_id' => 43, 'nombre_evento' => 'Paquete recibido en punto de recogida.'],
            (object) ['codigo_evento' => 31, 'nombre_evento' => 'Send item to customs (Inb)'],
        ], 'EMS');

        $this->assertTrue($progress['is_pickup_available']);
        $this->assertSame('Ventanilla = Aduana', $progress['steps'][$progress['current_index']]);
    }

    public function test_eme_marks_the_package_as_held_by_customs(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) ['event_tag' => 'EME', 'nombre_evento' => 'Held by import Customs'],
            (object) ['codigo_evento' => 31, 'nombre_evento' => 'Send item to customs (Inb)'],
        ], 'EMS');

        $this->assertSame(3, $progress['current_index']);
        $this->assertSame('Ventanilla = Aduana', $progress['steps'][$progress['current_index']]);
        $this->assertSame('Retenido por aduana', $progress['status']);
        $this->assertTrue($progress['is_customs']);
        $this->assertTrue($progress['is_held_by_customs']);
    }

    public function test_sitra_retention_reason_event_marks_the_package_as_held_by_customs(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) [
                'codigo_evento' => 6,
                'nombre_evento' => 'Registrar motivo de retencion de envio por parte de aduana (salida)',
            ],
        ], 'EMS');

        $this->assertSame('Aduana', $progress['steps'][$progress['current_index']]);
        $this->assertSame('Retenido por aduana', $progress['status']);
        $this->assertTrue($progress['is_customs']);
        $this->assertTrue($progress['is_held']);
        $this->assertTrue($progress['is_held_by_customs']);
    }

    public function test_receptacle_customs_custody_event_does_not_mark_individual_package_as_held(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) [
                'codigo_evento' => 194,
                'nombre_evento' => 'Entregado bajo custodia de aduana o seguridad',
            ],
        ], 'EMS');

        $this->assertNotContains('Aduana', $progress['steps']);
        $this->assertSame('En transito', $progress['status']);
        $this->assertFalse($progress['is_customs']);
        $this->assertFalse($progress['is_held']);
        $this->assertFalse($progress['is_held_by_customs']);
        $this->assertFalse($progress['is_in_customs_custody']);
    }

    public function test_observed_at_customs_event_does_not_by_itself_mean_held(): void
    {
        $progress = app(TrackingProgressService::class)->resolve([
            (object) [
                'codigo_evento' => 1251,
                'nombre_evento' => 'Insertar envío observado en Aduana en saca nacional',
            ],
        ], 'EMS');

        $this->assertSame('En transito con incidencia', $progress['status']);
        $this->assertFalse($progress['is_customs']);
        $this->assertFalse($progress['is_held'] ?? false);
    }
}
