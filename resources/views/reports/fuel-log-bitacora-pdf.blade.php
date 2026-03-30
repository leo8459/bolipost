<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Planilla de uso de combustibles</title>
    <style>
        @page { margin: 8mm; }
        body { font-family: Arial, sans-serif; font-size: 10px; color: #111; margin: 0; }
        .title-top { text-align: center; font-size: 12px; font-weight: 700; margin-bottom: 2px; }
        .title-main { text-align: center; font-size: 16px; font-weight: 800; margin-bottom: 4px; text-transform: uppercase; }
        .filters { font-size: 10px; margin-bottom: 8px; color: #333; }
        .sheet-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .sheet-table th, .sheet-table td { border: 1px solid #222; padding: 4px; vertical-align: top; }
        .sheet-table th { background: #c3c7cc; text-transform: uppercase; text-align: center; font-size: 9px; }
        .num { text-align: right; }
        .totals td { font-weight: 700; background: #f0f2f4; }
        .small { font-size: 9px; color: #444; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .legacy-title { text-align: center; font-weight: 700; font-size: 14px; margin-bottom: 2px; text-transform: uppercase; }
        .legacy-subtitle { text-align: center; font-weight: 700; font-size: 18px; margin: 2px 0 6px; }
        .legacy-meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .legacy-meta td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .legacy-label { font-weight: 700; display: block; font-size: 10px; margin-bottom: 2px; }
        .legacy-value { font-weight: 700; font-size: 12px; text-transform: uppercase; }
        .legacy-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .legacy-table th, .legacy-table td { border: 1px solid #000; padding: 4px; }
        .legacy-table thead th { background: #d2d8df; font-size: 10px; text-transform: uppercase; }
        .legacy-table .subhead th { background: #e4e8ec; font-size: 10px; }
    </style>
</head>
<body>
@php
    $reportMode = $reportMode ?? 'fuel_bitacora';
@endphp

@if($reportMode === 'fuel_bitacora')
    @php
        $columnLabels = [
            'station_name' => 'Estacion de servicio',
            'invoice_number' => 'Numero de factura',
            'regional' => 'Regional',
            'fecha_carga' => 'Fecha de carga',
            'litros' => 'Litros',
            'importe_bs' => 'Importe Bs.',
            'total_km' => 'Total km recorrido',
            'placa' => 'N de placa',
            'vehiculo' => 'Vehiculo',
            'driver_name' => 'Nombre del conductor',
        ];
        $visibleColumns = collect($visibleColumns ?? array_keys($columnLabels))
            ->filter(fn ($column) => array_key_exists($column, $columnLabels))
            ->values();
        $columnCount = max($visibleColumns->count(), 1);
    @endphp
    <div class="title-top">ANEXO 2</div>
    <div class="title-main">Planilla de uso de combustibles (gasolina)</div>

    <div class="filters">
        Generado: {{ $generatedAt->format('d/m/Y H:i') }}
        | Desde: {{ $fechaDesde ?: 'Todos' }}
        | Hasta: {{ $fechaHasta ?: 'Todos' }}
        | Placa filtro: {{ $placaFiltro !== '' ? $placaFiltro : 'Todas' }}
        | Vehiculo: {{ ($selectedVehicleLabel ?? '') !== '' ? $selectedVehicleLabel : 'Todos' }}
        | Conductor: {{ ($selectedDriverLabel ?? '') !== '' ? $selectedDriverLabel : 'Todos' }}
    </div>

    <table class="sheet-table">
        <thead>
            <tr>
                @foreach($visibleColumns as $column)
                    <th>{{ $columnLabels[$column] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($visibleColumns as $column)
                        @if($column === 'litros')
                            <td class="num">{{ ($row['litros'] ?? 0) > 0 ? number_format((float) $row['litros'], 3) : '-' }}</td>
                        @elseif($column === 'importe_bs')
                            <td class="num">{{ ($row['importe_bs'] ?? 0) > 0 ? number_format((float) $row['importe_bs'], 2) : '-' }}</td>
                        @elseif($column === 'total_km')
                            <td class="num">{{ ($row['total_km'] ?? null) !== null ? number_format((float) $row['total_km'], 3) : '-' }}</td>
                        @elseif($column === 'driver_name')
                            <td>{{ strtoupper($row['driver_name'] ?: 'SIN CONDUCTOR') }}</td>
                        @else
                            <td>{{ $row[$column] ?: '-' }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $columnCount }}">No existen registros para los filtros seleccionados.</td>
                </tr>
            @endforelse
            @if(($rows ?? collect())->count() > 0)
                <tr class="totals">
                    @foreach($visibleColumns as $index => $column)
                        @if($index === 0)
                            <td>Totales</td>
                        @elseif($column === 'litros')
                            <td class="num">{{ number_format((float) ($totals['litros'] ?? 0), 3) }}</td>
                        @elseif($column === 'importe_bs')
                            <td class="num">{{ number_format((float) ($totals['importe_bs'] ?? 0), 2) }}</td>
                        @elseif($column === 'total_km')
                            <td class="num">{{ number_format((float) ($totals['total_km'] ?? 0), 3) }}</td>
                        @else
                            <td></td>
                        @endif
                    @endforeach
                </tr>
            @endif
        </tbody>
    </table>

    <div class="small" style="margin-top: 8px;">Documento generado por el sistema de bitacora.</div>
@else
    @php
        $visibleColumns = collect($visibleColumns ?? [
            'fecha',
            'placa',
            'vehiculo',
            'driver_name',
            'kilometraje_salida',
            'kilometraje_recorrido',
            'kilometraje_llegada',
            'recorrido',
            'combustible',
        ])->values();
        $showKmSalida = $visibleColumns->contains('kilometraje_salida');
        $showKmRecorrido = $visibleColumns->contains('kilometraje_recorrido');
        $showKmLlegada = $visibleColumns->contains('kilometraje_llegada');
        $showRecorrido = $visibleColumns->contains('recorrido');
    @endphp
    @forelse($groups as $group)
        @php
            $chunks = ($group['row_chunks'] ?? collect())->count() > 0 ? $group['row_chunks'] : collect([$group['rows'] ?? collect()]);
        @endphp
        @foreach($chunks as $rowsChunk)
            <div class="page">
                <div class="legacy-title">ANEXO 2</div>
                <div class="legacy-subtitle">FORMULARIO DE BITACORA DE CONTROL PARA PROVISION DE COMBUSTIBLE</div>

                <div class="filters">
                    Generado: {{ $generatedAt->format('d/m/Y H:i') }}
                    | Desde: {{ $fechaDesde ?: 'Todos' }}
                    | Hasta: {{ $fechaHasta ?: 'Todos' }}
                    | Placa filtro: {{ $placaFiltro !== '' ? $placaFiltro : 'Todas' }}
                    | Vehiculo: {{ ($selectedVehicleLabel ?? '') !== '' ? $selectedVehicleLabel : 'Todos' }}
                    | Conductor: {{ ($selectedDriverLabel ?? '') !== '' ? $selectedDriverLabel : 'Todos' }}
                </div>

                <table class="legacy-meta">
                    <tr>
                        <td>
                            <span class="legacy-label">CONDUCTOR</span>
                            <div class="legacy-value">
                                {{ strtoupper((string) ($group['driver']?->nombre ?? 'SIN CONDUCTOR DESIGNADO')) }}
                            </div>
                        </td>
                        <td>
                            <span class="legacy-label">PLACA</span>
                            <div class="legacy-value">{{ $group['vehicle']?->placa ?? 'SIN PLACA' }}</div>
                        </td>
                        <td>
                            <span class="legacy-label">VEHICULO</span>
                            <div class="legacy-value">{{ trim(($group['vehicle']?->brand?->nombre ?? '') . ' ' . ($group['vehicle']?->modelo ?? '')) ?: '-' }}</div>
                        </td>
                    </tr>
                </table>

                <table class="legacy-table">
                    <thead>
                        <tr>
                            @if($visibleColumns->contains('fecha'))
                                <th>Fecha</th>
                            @endif
                            @if($visibleColumns->contains('placa'))
                                <th>Placa</th>
                            @endif
                            @if($visibleColumns->contains('vehiculo'))
                                <th>Vehiculo</th>
                            @endif
                            @if($visibleColumns->contains('driver_name'))
                                <th>Conductor</th>
                            @endif
                            @if($showKmSalida || $showKmRecorrido || $showKmLlegada)
                                <th colspan="{{ ($showKmSalida ? 1 : 0) + ($showKmRecorrido ? 1 : 0) + ($showKmLlegada ? 1 : 0) }}">Kilometraje</th>
                            @endif
                            @if($showRecorrido)
                                <th>Recorrido</th>
                            @endif
                            @if($visibleColumns->contains('combustible'))
                                <th>Abastecimiento de Combustible</th>
                            @endif
                        </tr>
                        <tr class="subhead">
                            @if($visibleColumns->contains('fecha'))
                                <th></th>
                            @endif
                            @if($visibleColumns->contains('placa'))
                                <th></th>
                            @endif
                            @if($visibleColumns->contains('vehiculo'))
                                <th></th>
                            @endif
                            @if($visibleColumns->contains('driver_name'))
                                <th></th>
                            @endif
                            @if($showKmSalida)
                                <th>Salida</th>
                            @endif
                            @if($showKmRecorrido)
                                <th>Recorrido</th>
                            @endif
                            @if($showKmLlegada)
                                <th>Final</th>
                            @endif
                            @if($showRecorrido)
                                <th></th>
                            @endif
                            @if($visibleColumns->contains('combustible'))
                                <th></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($rowsChunk as $row)
                            @php
                                $kmSalida = $row->kilometraje_salida !== null ? (float) $row->kilometraje_salida : null;
                                $kmLlegada = $row->kilometraje_llegada !== null ? (float) $row->kilometraje_llegada : null;
                                $kmRecorrido = $row->kilometraje_recorrido !== null
                                    ? (float) $row->kilometraje_recorrido
                                    : (($kmSalida !== null && $kmLlegada !== null) ? max(0, $kmLlegada - $kmSalida) : null);
                                $litros = (float) ($row->fuelLog?->cantidad ?? $row->fuelLog?->galones ?? 0);
                            @endphp
                            <tr>
                                @if($visibleColumns->contains('fecha'))
                                    <td>{{ optional($row->fecha)->format('d/m/y') ?? '-' }}</td>
                                @endif
                                @if($visibleColumns->contains('placa'))
                                    <td>{{ $row->vehicle?->placa ?? '-' }}</td>
                                @endif
                                @if($visibleColumns->contains('vehiculo'))
                                    <td>{{ trim(($row->vehicle?->brand?->nombre ?? '') . ' ' . ($row->vehicle?->modelo ?? '')) ?: '-' }}</td>
                                @endif
                                @if($visibleColumns->contains('driver_name'))
                                    <td>{{ strtoupper((string) ($row->driver?->nombre ?? 'SIN CONDUCTOR')) }}</td>
                                @endif
                                @if($showKmSalida)
                                    <td class="num">{{ $kmSalida !== null ? number_format($kmSalida, 2) : '-' }}</td>
                                @endif
                                @if($showKmRecorrido)
                                    <td class="num">{{ $kmRecorrido !== null ? number_format($kmRecorrido, 2) : '-' }}</td>
                                @endif
                                @if($showKmLlegada)
                                    <td class="num">{{ $kmLlegada !== null ? number_format($kmLlegada, 2) : '-' }}</td>
                                @endif
                                @if($showRecorrido)
                                    <td>{{ trim(((string) ($row->recorrido_inicio ?? '-')) . ' -> ' . ((string) ($row->recorrido_destino ?? '-'))) }}</td>
                                @endif
                                @if($visibleColumns->contains('combustible'))
                                    <td class="num">{{ $litros > 0 ? 'Si - ' . number_format($litros, 2) . ' L' : 'No' }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @empty
        <div class="page">
            <div class="legacy-title">ANEXO 2</div>
            <div class="legacy-subtitle">FORMULARIO DE BITACORA DE CONTROL PARA PROVISION DE COMBUSTIBLE</div>
            <p>No existen registros para los filtros seleccionados.</p>
        </div>
    @endforelse
@endif
</body>
</html>
