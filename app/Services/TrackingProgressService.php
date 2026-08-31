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
        8 => self::STAGE_EXPEDITION,
        12 => self::STAGE_EXPEDITION,
        30 => self::STAGE_EXPEDITION,
        32 => self::STAGE_COUNTER,
        35 => self::STAGE_EXPEDITION,
        36 => self::STAGE_COURIER,
        37 => self::STAGE_DELIVERED,
        39 => self::STAGE_COURIER,
        40 => self::STAGE_EXPEDITION,
        42 => self::STAGE_EXPEDITION,
        43 => self::STAGE_EXPEDITION,
        44 => self::STAGE_EXPEDITION,
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

        $steps = [$firstStep, 'Despacho', 'Expedicion', 'Ventanilla'];
        if ($hasCourierEvent || $highestStage >= self::STAGE_COURIER) {
            $steps[] = 'Cartero';
        }
        $steps[] = 'Entregado';

        if ($isCancelled) {
            $steps[1] = 'Cancelado';

            return [
                'steps' => $steps,
                'current_index' => 1,
                'has_incident' => true,
                'is_cancelled' => true,
                'status' => 'Envio cancelado',
            ];
        }

        $currentIndex = $this->stepIndex($highestStage, $hasCourierEvent || $highestStage >= self::STAGE_COURIER);
        return [
            'steps' => $steps,
            'current_index' => $currentIndex,
            'has_incident' => $hasIncident,
            'is_cancelled' => false,
            'status' => $highestStage === self::STAGE_DELIVERED
                ? 'Entregado'
                : ($hasIncident ? 'En transito con incidencia' : 'En transito'),
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

    private function stepIndex(int $stage, bool $hasCourierStep): int
    {
        if ($stage === self::STAGE_DELIVERED) {
            return $hasCourierStep ? 5 : 4;
        }

        if ($stage === self::STAGE_COURIER) {
            return 4;
        }

        return $stage;
    }

    private function isIncident(mixed $event): bool
    {
        return $this->containsAny($this->eventText((object) $event), [
            'fallido', 'incidencia', 'devuelto', 'devolucion', 'retorno', 'retenido', 'retener', 'detenida',
            'detenido', 'aduana', 'cancelado', 'cancelada', 'eliminado', 'eliminada',
        ]);
    }

    private function isCancelledText(string $text): bool
    {
        return $this->containsAny($text, ['envio cancelado', 'paquete cancelado']);
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
