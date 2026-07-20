<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Planilla de uso de combustibles</title>
    <style>
        @page { margin: 6mm; size: A4 landscape; }
        body { font-family: Verdana, DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 0; }
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
        $showFecha = $visibleColumns->contains('fecha');
        $showPlaca = $visibleColumns->contains('placa');
        $showVehiculo = $visibleColumns->contains('vehiculo');
        $showDriver = $visibleColumns->contains('driver_name');
        $showKmSalida = $visibleColumns->contains('kilometraje_salida');
        $showKmRecorrido = $visibleColumns->contains('kilometraje_recorrido');
        $showKmLlegada = $visibleColumns->contains('kilometraje_llegada');
        $showRecorrido = $visibleColumns->contains('recorrido');
        $showCombustible = $visibleColumns->contains('combustible');
            </div>
        @endforeach
    @empty
        <div class="page">
            <div class="legacy-subtitle">FORMULARIO DE BITÁCORA DE CONTROL PARA PROVISIÓN DE COMBUSTIBLE</div>
            <p>No existen registros para los filtros seleccionados.</p>
        </div>
    @endforelse
@endif
</body>
</html>
