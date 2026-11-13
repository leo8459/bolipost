<?php

namespace App\Services;

class IpsCombinedOfficeWorkbench
{
    public function __construct(private readonly SitraIpsClient $ips) {}

    /** Merge disjoint office queues before applying the shared page offset. */
    public function packages(array $filters, array $offices): array
    {
        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = (int) ($filters['per_page'] ?? 25);
        $needed = $page * $perPage;
        $items = [];
        $total = 0;
        $queues = [$filters];
        $events = (array) ($filters['event_cd'] ?? [13, 35, 38, 72]);
        if (($filters['status'] ?? '') === 'reception' && in_array(13, $events, false)) {
            // The pending sack queue also includes legacy sacks addressed to the
            // same office. The reception queue intentionally excludes those rows.
            $queues = [];
            $otherEvents = array_values(array_diff($events, [13]));
            if ($otherEvents !== []) {
                $queues[] = array_replace($filters, ['event_cd' => $otherEvents]);
            }
            $queues[] = array_replace($filters, ['status' => 'pending', 'event_cd' => 13]);
        }
        foreach ($offices as $office) {
            foreach ($queues as $queueIndex => $queueFilters) {
                $position = 0;
                $remoteSize = min(100, $needed);
                for ($remotePage = 1; ; $remotePage++) {
                    $result = $this->ips->packages(array_replace($queueFilters, [
                        'office_cd' => $office['office_cd'], 'page' => $remotePage, 'per_page' => $remoteSize,
                    ]));
                    $officeTotal = (int) ($result['meta']['total'] ?? 0);
                    if ($remotePage === 1) {
                        $total += $officeTotal;
                    }
                    foreach ($result['data'] ?? [] as $package) {
                        $package['_combined_queue_position'] = $position++;
                        $package['_combined_queue_index'] = $queueIndex;
                        $items[] = $this->withReceiptOptions($this->withOffice($package, $office), $offices);
                    }
                    if ($remotePage * $remoteSize >= min($needed, $officeTotal) || empty($result['data'])) {
                        break;
                    }
                }
            }
        }
        usort($items, fn ($a, $b) => (strtotime($b['event_at'] ?? '') <=> strtotime($a['event_at'] ?? ''))
            ?: ($a['workbench_office_cd'] <=> $b['workbench_office_cd'])
            ?: ($a['_combined_queue_index'] <=> $b['_combined_queue_index'])
            ?: ($a['_combined_queue_position'] <=> $b['_combined_queue_position']));
        $unique = collect($items)->unique('codigo')->values();
        $total -= count($items) - $unique->count();

        return [
            'data' => $unique->slice(($page - 1) * $perPage, $perPage)->map(function ($package) {
                unset($package['_combined_queue_position'], $package['_combined_queue_index']);

                return $package;
            })->values()->all(),
            'meta' => ['total' => $total, 'page' => $page, 'per_page' => $perPage],
        ];
    }

    public function package(string $code, array $offices, string $action, ?int $receiptOffice = null): array
    {
        $first = $offices[0];
        $detail = $this->ips->package($code, $first['office_cd']);
        $package = $detail['package'];
        $event = (int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0);
        $officeCd = $action === 'EMG' && in_array($event, [13, 35, 72], true)
            ? (int) ($package['next_office_cd'] ?? -1)
            : (int) ($package['operational_office_cd'] ?? $package['office_cd'] ?? -1);
        $office = collect($offices)->firstWhere('office_cd', $officeCd);
        abort_unless($office, 409, 'El paquete no está encaminado ni recibido en ninguna de tus oficinas de La Paz. Revisa su ruta en IPS.');
        $package = $this->withReceiptOptions($package, $offices);
        if ($receiptOffice !== null) {
            abort_unless($action === 'EMG' && in_array($receiptOffice, array_column($package['receipt_office_options'] ?? [], 'office_cd'), true),
                409, 'La oficina elegida no corresponde a las opciones de recepción física de este paquete.');
            $office = collect($offices)->firstWhere('office_cd', $receiptOffice);
        }
        if ($office['office_cd'] !== $first['office_cd']) {
            $detail = $this->ips->package($code, $office['office_cd']);
        }
        $detail['package'] = $this->withOffice($detail['package'], $office);
        if ($receiptOffice === null) {
            $detail['package'] = $this->withReceiptOptions($detail['package'], $offices);
        }

        return $detail;
    }

    private function withReceiptOptions(array $package, array $offices): array
    {
        $event = (int) ($package['operational_event_cd'] ?? $package['event_cd'] ?? 0);
        $current = (int) ($package['operational_office_cd'] ?? $package['office_cd'] ?? 0);
        $next = (int) ($package['next_office_cd'] ?? 0);
        if ($event === 13 && (int) ($package['state_cd'] ?? -1) === 6
            && $current > 0 && $current === $next && ($package['destination_country'] ?? '') === 'BO'
            && empty($package['terminal'])) {
            $package['receipt_office_options'] = array_values(array_filter($offices, fn ($office) => $office['office_cd'] !== $current));
            $package['receipt_office_note'] = 'IPS registra la saca con origen y destino en la misma oficina. La oficina de recepción se asigna automáticamente; confirma la llegada física del paquete en el modal.';
        }

        return $package;
    }

    private function withOffice(array $package, array $office): array
    {
        $package['workbench_office_cd'] = $office['office_cd'];
        $package['workbench_office_name'] = $office['office_name'];
        $package['workbench_office_fcd'] = $office['office_fcd'];

        return $package;
    }
}
