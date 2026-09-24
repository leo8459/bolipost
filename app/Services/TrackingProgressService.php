<?php

namespace App\Services;

class TrackingProgressService
{
    private const STAGE_ADMISSION = 0;
    private const STAGE_DISPATCH = 1;
    private const STAGE_EXPEDITION = 2;
    private const STAGE_COUNTER = 3;
    private const STAGE_COURIER = 4;
    private const STAGE_DELIVERED = 5;

    /** @var array<int, int> */
    private const UPU_STAGES = [
        1 => self::STAGE_ADMISSION,
        2 => self::STAGE_DISPATCH,
        3 => self::STAGE_DISPATCH,
        5 => self::STAGE_DISPATCH,
        4 => self::STAGE_EXPEDITION,
        6 => self::STAGE_EXPEDITION,
        7 => self::STAGE_EXPEDITION,
        8 => self::STAGE_EXPEDITION,
        12 => self::STAGE_EXPEDITION,
        30 => self::STAGE_EXPEDITION,
        32 => self::STAGE_COUNTER,
        35 => self::STAGE_EXPEDITION,
        36 => self::STAGE_COURIER,
        37 => self::STAGE_DELIVERED,
        38 => self::STAGE_EXPEDITION,
        39 => self::STAGE_COURIER,
        40 => self::STAGE_EXPEDITION,
        42 => self::STAGE_EXPEDITION,
        43 => self::STAGE_EXPEDITION,
        44 => self::STAGE_EXPEDITION,
        45 => self::STAGE_ADMISSION,
        68 => self::STAGE_ADMISSION,
        67 => self::STAGE_COURIER,
        71 => self::STAGE_EXPEDITION,
        72 => self::STAGE_EXPEDITION,
        73 => self::STAGE_COUNTER,
        74 => self::STAGE_COURIER,
        75 => self::STAGE_COUNTER,
        77 => self::STAGE_EXPEDITION,
        78 => self::STAGE_ADMISSION,
        1250 => self::STAGE_DELIVERED,
    ];

    /** @var array<int, int> */
    private const LOCAL_STAGES = [
        165 => self::STAGE_COURIER,
        181 => self::STAGE_DISPATCH,
        182 => self::STAGE_EXPEDITION,
        183 => self::STAGE_COUNTER,
        184 => self::STAGE_COURIER,
        185 => self::STAGE_COUNTER,
        295 => self::STAGE_ADMISSION,
        296 => self::STAGE_DISPATCH,
        297 => self::STAGE_DISPATCH,
        299 => self::STAGE_DISPATCH,
        302 => self::STAGE_EXPEDITION,
        306 => self::STAGE_EXPEDITION,
        310 => self::STAGE_EXPEDITION,
        312 => self::STAGE_COUNTER,
        315 => self::STAGE_COURIER,
        316 => self::STAGE_DELIVERED,
        319 => self::STAGE_EXPEDITION,
        2238 => self::STAGE_COUNTER,
        2239 => self::STAGE_ADMISSION,
        2240 => self::STAGE_DISPATCH,
        2241 => self::STAGE_EXPEDITION,
        2242 => self::STAGE_DISPATCH,
        4477 => self::STAGE_COURIER,
        4478 => self::STAGE_COUNTER,
    ];

    public function resolve(iterable $events, string $service): array
    {
        $events = collect($events);
        $firstStep = in_array(strtoupper($service), ['ORDI', 'CERTI'], true) ? 'Clasificacion' : 'Admision';
        $isCancelled = $events->isNotEmpty() && $this->isCancelledText($this->eventText((object) $events->first()));
        // El estado actual lo determina el evento más reciente. Un evento
        // antiguo de devolución no debe ocultar una entrega posterior.
        $latestEvent = $events->first();
        $isReturning = $latestEvent !== null && $this->isReturnStatus((object) $latestEvent);
        $isHeldByCustoms = $latestEvent !== null && $this->isHeldByCustoms((object) $latestEvent);
        $isHeldAtExchange = $latestEvent !== null && $this->isHeldAtExchange((object) $latestEvent);
        $isInCustoms = $events->isNotEmpty() && $this->isCurrentlyInCustoms((object) $events->first());
        $hasCustomsStep = $events->contains(fn ($event) => $this->isCustomsEvent((object) $event));
        $hasCustomsCounterFlow = $events->contains(fn ($event) => $this->isInboundCustomsFlowEvent((object) $event));
        $customsActionRequired = $latestEvent !== null && $this->isCustomsRecipientAction((object) $latestEvent);
        $customsPurchaseProofRequired = $latestEvent !== null && $this->hasCustomsPurchaseProofReason((object) $latestEvent);
        $pickupReadyAtDestination = $latestEvent !== null
            && $this->isCustomsReadyAtDestination((object) $latestEvent, $events);
        $pickupAvailable = $latestEvent !== null
            && ($this->isPickupAvailable((object) $latestEvent) || $pickupReadyAtDestination);
        if ($pickupReadyAtDestination && !$isHeldByCustoms && !$customsActionRequired) {
            $isInCustoms = false;
        }
        $customsReturnedToPostalFlow = $latestEvent !== null && $this->isCustomsReturnedToPostalFlow((object) $latestEvent);
        $highestStage = self::STAGE_ADMISSION;
        $hasCourierEvent = false;
        $hasIncident = false;

        foreach ($events as $event) {
            $stage = $this->stageFor($event);
            if ($stage !== null) {
                $highestStage = max($highestStage, $stage);
                $hasCourierEvent = $hasCourierEvent || $stage === self::STAGE_COURIER;
            }

            $hasIncident = $hasIncident || $this->isIncident($event);
        }

        if ($isCancelled) {
            $steps = [$firstStep, 'Cancelado', 'Expedicion', 'Ventanilla', 'Entregado'];

            return [
                'steps' => $steps,
                'current_index' => 1,
                'has_incident' => true,
                'is_cancelled' => true,
                'is_customs' => false,
                'is_returning' => false,
                'status' => 'Envio cancelado',
            ];
        }

        $steps = [$firstStep, 'Despacho', 'Expedicion'];
        if ($hasCustomsStep) {
            $steps[] = $hasCustomsCounterFlow && !$isReturning ? 'Ventanilla = Aduana' : 'Aduana';
        }

        if ($isReturning) {
            // Una devolución es un flujo terminal distinto al de entrega:
            // conserva el intento de cartero si existe, pero no agrega
            // ventanilla ni entrega como etapas pendientes.
            if ($hasCourierEvent) {
                $steps[] = 'Cartero';
            }
            $steps[] = 'Devolución';
            $returnStatus = $this->returnStatusCode($events);
            $isReturnCompleted = in_array($returnStatus, [22, 23], true);
            // Las etapas futuras se muestran como pendientes para que el
            // usuario entienda el flujo esperado; no representan eventos ya
            // registrados en IPS.
            $steps[] = 'Retorno recibido';
            $steps[] = 'Devuelto al remitente';

            return [
                'steps' => $steps,
                'current_index' => $isReturnCompleted
                    ? count($steps) - 1
                    : count($steps) - 3,
                'has_incident' => true,
                'is_cancelled' => false,
                'is_customs' => $isInCustoms,
                'is_returning' => true,
                'return_status_cd' => $returnStatus,
                'is_return_completed' => $isReturnCompleted,
                'status' => $isReturnCompleted ? 'Devuelto al remitente' : 'Devolución en curso',
            ];
        }

        if (!$hasCustomsCounterFlow) {
            $steps[] = 'Ventanilla';
        }
        if ($hasCourierEvent) {
            $steps[] = 'Cartero';
        }
        $steps[] = 'Entregado';

        $isDelivered = $highestStage === self::STAGE_DELIVERED;
        $customsCounterCurrent = !$isDelivered && $hasCustomsCounterFlow
            && ($isInCustoms
                || $pickupReadyAtDestination
                || ($latestEvent !== null && $this->numericValue(((object) $latestEvent)->codigo_evento ?? null) === 38)
                || $highestStage >= self::STAGE_COUNTER);
        $currentIndex = $isDelivered
            ? count($steps) - 1
            : ($customsCounterCurrent
                ? 3
                : ($isInCustoms
                    ? 3
                    : $this->stepIndex(
                    $highestStage,
                    $hasCourierEvent,
                    $hasCustomsStep && !$hasCustomsCounterFlow
                )));

        return [
            'steps' => $steps,
            'current_index' => $currentIndex,
            'has_incident' => $hasIncident || $isHeldByCustoms || $isHeldAtExchange,
            'is_cancelled' => false,
            'is_customs' => $isInCustoms,
            'has_customs_counter_flow' => $hasCustomsCounterFlow,
            'customs_action_required' => $customsActionRequired,
            'customs_purchase_proof_required' => $customsPurchaseProofRequired,
            'is_pickup_available' => $pickupAvailable,
            'pickup_ready_at_destination_customs' => $pickupReadyAtDestination,
            'customs_returned_to_postal_flow' => $customsReturnedToPostalFlow,
            'is_held' => $isHeldByCustoms || $isHeldAtExchange,
            'is_held_by_customs' => $isHeldByCustoms,
            'is_in_customs_custody' => false,
            'is_returning' => $isReturning,
            'status' => $isHeldByCustoms
                ? 'Retenido por aduana'
                : ($pickupReadyAtDestination && !$customsActionRequired
                    ? 'Listo para recoger'
                    : ($isInCustoms
                    ? 'En Aduana'
                    : ($isHeldAtExchange
                    ? 'Retenido en oficina de cambio'
                    : ($isReturning
                        ? 'Devolución en curso'
                        : ($isDelivered
                            ? 'Entregado'
                            : ($hasIncident ? 'En transito con incidencia' : 'En transito')))))),
        ];
    }

    private function stageFor(mixed $event): ?int
    {
        $event = (object) $event;
        $upuCode = $this->numericValue($event->codigo_evento ?? null);
        if ($upuCode !== null && array_key_exists($upuCode, self::UPU_STAGES)) {
            return self::UPU_STAGES[$upuCode];
        }

        $localEventId = $this->numericValue($event->evento_id ?? null);
        if ($localEventId !== null && array_key_exists($localEventId, self::LOCAL_STAGES)) {
            return self::LOCAL_STAGES[$localEventId];
        }

        return $this->stageFromText($this->eventText($event));
    }

    private function stageFromText(string $text): ?int
    {
        if ($this->isDeliveredText($text)) {
            return self::STAGE_DELIVERED;
        }

        if ($this->containsAny($text, ['cartero', 'agente de entrega', 'entrega fisica', 'intento fallido'])) {
            return self::STAGE_COURIER;
        }

        if ($this->containsAny($text, ['ventanilla', 'listo para entregar', 'oficina de entrega', 'punto de entrega', 'punto de recogida'])) {
            return self::STAGE_COUNTER;
        }

        if ($this->containsAny($text, ['expedicion', 'saca', 'transito', 'extranjero', 'oficina de cambio', 'ubicacion nacional'])) {
            return self::STAGE_EXPEDITION;
        }

        if ($this->containsAny($text, ['despacho', 'centro de clasificacion', 'centro de procesamiento'])) {
            return self::STAGE_DISPATCH;
        }

        if ($this->containsAny($text, ['admision', 'recibir envio del cliente', 'paquete recibido del cliente'])) {
            return self::STAGE_ADMISSION;
        }

        return null;
    }

    private function stepIndex(int $stage, bool $hasCourierStep, bool $hasCustomsStep): int
    {
        $customsOffset = $hasCustomsStep && $stage >= self::STAGE_COUNTER ? 1 : 0;

        if ($stage === self::STAGE_DELIVERED) {
            return ($hasCourierStep ? 5 : 4) + $customsOffset;
        }

        if ($stage === self::STAGE_COURIER) {
            return 4 + $customsOffset;
        }

        return $stage + $customsOffset;
    }

    private function isIncident(mixed $event): bool
    {
        if ($this->numericValue(((object) $event)->codigo_evento ?? null) === 1251) {
            return true;
        }

        // 194 se registra sobre sacas/receptÃ¡culos (RC), no sobre el paquete (MI).
        if (in_array($this->numericValue(((object) $event)->codigo_evento ?? null), [38, 194], true)) {
            return false;
        }

        return $this->containsAny($this->eventText((object) $event), [
            'fallido', 'incidencia', 'devuelto', 'devolver', 'devolucion', 'retorno', 'retenido', 'retener', 'detenida',
            'detenido', 'cancelado', 'cancelada', 'eliminado', 'eliminada',
        ]);
    }

    private function isCancelledText(string $text): bool
    {
        return $this->containsAny($text, ['envio cancelado', 'paquete cancelado']);
    }

    private function isReturnStatus(object $event): bool
    {
        $code = $this->numericValue(
            $event->postal_status_cd ?? $event->codigo_estado_postal ?? null
        );
        if (in_array($code, [6, 7, 22, 23], true)) {
            return true;
        }

        return $this->containsAny($this->normalize((string) (
            $event->postal_status ?? $event->estado_postal ?? ''
        )), [
            'being returned', 'return in progress', 'devolucion en curso',
            'devolucion', 'en devolucion', 'retorno al remitente',
        ]);
    }

    private function returnStatusCode(iterable $events): ?int
    {
        foreach ($events as $event) {
            $code = $this->numericValue(((object) $event)->postal_status_cd ?? null);
            if (in_array($code, [6, 7, 22, 23], true)) {
                return $code;
            }
        }

        return null;
    }

    private function isCustomsEvent(object $event): bool
    {
        // SITRA/IPS uses numeric event types; EME is the UPU hold tag.
        if ($this->eventCode($event) === 'EME'
            || in_array($this->numericValue($event->codigo_evento ?? null), [4, 6, 31, 34, 38, 76], true)) {
            return true;
        }

        return $this->containsAny($this->eventText($event), [
            'send item to customs', 'record item customs information', 'stop item import',
            'enviado a control aduanero', 'enviado a aduana', 'registrar informacion de aduanas',
            'held by customs', 'held by import customs', 'retenido por aduana',
            'retenido en aduana', 'retener envio en aduana', 'retencion aduanera',
        ]);
    }

    private function isInboundCustomsFlowEvent(object $event): bool
    {
        if (in_array($this->numericValue($event->codigo_evento ?? null), [31, 34, 38, 76], true)) {
            return true;
        }

        return $this->eventCode($event) === 'EME';
    }

    private function isCustomsRecipientAction(object $event): bool
    {
        if ($this->isHeldByCustoms($event)) {
            return true;
        }

        return $this->hasCustomsPurchaseProofReason($event);
    }

    private function hasCustomsPurchaseProofReason(object $event): bool
    {
        foreach (['retention_reason_cd', 'RETENTION_REASON_CD', 'retentionReasonCode'] as $field) {
            if ($this->numericValue($event->{$field} ?? null) === 65) {
                return true;
            }
        }

        return $this->containsAny($this->eventText($event), [
            'awaiting proof of purchase', 'proof of purchase/value',
            'a la espera de prueba de compra', 'prueba de compra/valor',
        ]);
    }

    private function isPickupAvailable(object $event): bool
    {
        // En el flujo local de estos envíos, el 32 significa que ya llegó a Ventanilla.
        if (in_array($this->numericValue($event->codigo_evento ?? null), [32, 75], true)) {
            return true;
        }

        return $this->containsAny($this->eventText($event), [
            'listo para entregar', 'listo para recoger', 'oficina de entrega', 'ventanilla',
            'punto de recogida', 'received at collection point', 'receive item at collection point',
            'available for collection', 'item available for collection',
            'collection point for pick-up', 'collection point for pickup',
        ]);
    }

    private function isCustomsReadyAtDestination(object $event, iterable $events): bool
    {
        // IPS code 34 records customs information, not a generic pickup event.
        // In this postal flow, it indicates the combined Aduana/Ventanilla
        // handoff only when the event is at the package's registered destination.
        if ($this->numericValue($event->codigo_evento ?? null) !== 34
            || $this->isHeldByCustoms($event)
            || $this->isCustomsRecipientAction($event)) {
            return false;
        }

        $destination = '';
        foreach ($events as $candidate) {
            $value = trim((string) (((object) $candidate)->ciudad_destino ?? ''));
            if ($value !== '') {
                $destination = $this->normalize($value);
                break;
            }
        }

        if ($destination === '' || in_array($destination, ['bolivia', 'plurinational state of bolivia'], true)) {
            return false;
        }

        $office = $this->normalize((string) ($event->office ?? $event->next_office ?? ''));
        if ($office === '') {
            return false;
        }

        $destination = preg_replace('/[^a-z0-9]+/u', ' ', $destination) ?? $destination;
        $office = preg_replace('/[^a-z0-9]+/u', ' ', $office) ?? $office;
        $destination = trim(preg_replace('/\s+/u', ' ', $destination) ?? $destination);
        $office = trim(preg_replace('/\s+/u', ' ', $office) ?? $office);

        return $destination !== '' && str_contains($office, $destination);
    }

    private function isCurrentlyInCustoms(object $event): bool
    {
        // 38 devuelve el envío al flujo postal; no significa que siga en Aduana.
        if ($this->numericValue($event->codigo_evento ?? null) === 38
            || $this->containsAny($this->eventText($event), [
                'return item from customs', 'returned from customs', 'devolver envio desde aduana',
            ])) {
            return false;
        }

        return $this->isCustomsEvent($event);
    }

    private function isCustomsReturnedToPostalFlow(object $event): bool
    {
        return $this->numericValue($event->codigo_evento ?? null) === 38
            || $this->containsAny($this->eventText($event), [
                'return item from customs', 'returned from customs', 'devolver envio desde aduana',
            ]);
    }

    private function isHeldByCustoms(object $event): bool
    {
        return $this->hasCustomsPurchaseProofReason($event)
            || $this->eventCode($event) === 'EME'
            || $this->numericValue($event->codigo_evento ?? null) === 6
            || $this->containsAny($this->eventText($event), [
                'held by customs', 'held by import customs', 'retenido por aduana',
                'retenido en aduana', 'retener envio en aduana', 'retencion aduanera',
                'reason for retention by customs', 'motivo de retencion de envio por parte de aduana',
            ]);
    }

    private function isHeldAtExchange(object $event): bool
    {
        $text = $this->eventText($event);

        return $this->numericValue($event->codigo_evento ?? null) === 70
            && $this->containsAny($text, ['retener', 'retenido', 'retain', 'held']);
    }

    private function eventCode(object $event): string
    {
        foreach (['event_tag', 'eventTag', 'event_code', 'eventCode', 'codigo_evento'] as $field) {
            $value = strtoupper(trim((string) ($event->{$field} ?? '')));
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    private function isDeliveredText(string $text): bool
    {
        if ($this->containsAny($text, ['listo para entregar', 'oficina de entrega', 'intento fallido', 'no entregado', 'pendiente de entrega'])) {
            return false;
        }

        return $this->containsAny($text, [
            'entregado exitosamente', 'entregado al cliente', 'entregado al destinatario', 'entrega realizada',
            'envio entregado', 'paquete entregado', 'recepcionado por destinatario', 'recibido por destinatario',
            'entregar envio', 'entregar envio con firma',
        ]);
    }

    private function eventText(object $event): string
    {
        return $this->normalize((string) ($event->nombre_evento ?? ''));
    }

    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_D) ?: $value;
            $value = preg_replace('/\p{Mn}+/u', '', $value) ?: $value;
        }

        return $value;
    }

    private function numericValue(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }
}
