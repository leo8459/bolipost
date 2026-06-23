<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Planilla de uso de combustibles</title>
    <style>
        @page { margin: 8mm; }
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
        $logoPath = public_path('images/AGBClogo1.png');
        $logoData = file_exists($logoPath)
            ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
            : null;
    @endphp
    @forelse($groups as $group)
        @php
            $chunks = ($group['row_chunks'] ?? collect())->count() > 0 ? $group['row_chunks'] : collect([$group['rows'] ?? collect()]);
        @endphp
        @foreach($chunks as $rowsChunk)
            <div class="page">
                @php
                    $displayRows = $rowsChunk->values();
                    $minimumRows = 10;
                    $blankRows = max(0, $minimumRows - $displayRows->count());
                    $vehicleLabel = trim(($group['vehicle']?->brand?->nombre ?? '') . ' ' . ($group['vehicle']?->modelo ?? '')) ?: '-';
                    $driverLabel = strtoupper((string) ($group['driver']?->nombre ?? 'SIN CONDUCTOR DESIGNADO'));
                @endphp

                <table style="width:100%; border-collapse:collapse; margin-bottom:8px;">
                    <tr>
                        <td style="width:22%; text-align:left; vertical-align:top;">
                            @if($logoData)
                                <img src="{{ $logoData }}" alt="Correos de Bolivia" style="max-width:150px; max-height:52px;">
                            @endif
                        </td>
                        <td style="width:56%; text-align:center; vertical-align:bottom;">
                            <div style="font-size:15px; font-weight:700; text-transform:uppercase; margin-top:22px;">
                                Formulario de bitácora de control para provisión de combustible
                            </div>
                        </td>
                        <td style="width:22%; text-align:right; vertical-align:top;">
                            @if($logoData)
                                <img src="{{ $logoData }}" alt="Correos de Bolivia" style="max-width:150px; max-height:52px;">
                            @endif
                        </td>
                    </tr>
                </table>

                <table class="legacy-meta" style="margin-bottom:10px;">
                    <tr>
                        <td style="width:40%;">
                            <span class="legacy-label">Conductor:</span>
                            <div class="legacy-value">{{ $driverLabel }}</div>
                        </td>
                        <td style="width:20%;">
                            <span class="legacy-label">Placa:</span>
                            <div class="legacy-value">{{ $group['vehicle']?->placa ?? 'SIN PLACA' }}</div>
                        </td>
                        <td style="width:40%;">
                            <span class="legacy-label">Vehículo:</span>
                            <div class="legacy-value">{{ strtoupper($vehicleLabel) }}</div>
                        </td>
                    </tr>
                </table>

                <table class="legacy-table" style="table-layout:fixed;">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:7%;">Fecha</th>
                            <th rowspan="2" style="width:7%;">Cantidad de litros</th>
                            <th rowspan="2" style="width:8%;">Numero de factura</th>
                            <th colspan="2" style="width:20%;">Kilometraje</th>
                            <th rowspan="2" style="width:9%;">Total recorrido (Km)</th>
                            <th colspan="2" style="width:26%;">Recorrido</th>
                            <th rowspan="2" style="width:11%;">Cantidad de guias llevadas y/o recogidas</th>
                            <th rowspan="2" style="width:7%;">Firma</th>
                        </tr>
                        <tr class="subhead">
                            <th>Salida</th>
                            <th>Llegada</th>
                            <th>Inicio</th>
                            <th>Destino</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($displayRows as $row)
                            @php
                                $kmSalida = $row->kilometraje_salida !== null ? (float) $row->kilometraje_salida : null;
                                $kmLlegada = $row->kilometraje_llegada !== null ? (float) $row->kilometraje_llegada : null;
                                $kmRecorrido = $row->kilometraje_recorrido !== null
                                    ? (float) $row->kilometraje_recorrido
                                    : (($kmSalida !== null && $kmLlegada !== null) ? max(0, $kmLlegada - $kmSalida) : null);
                                $litros = (float) ($row->fuelLog?->cantidad ?? $row->fuelLog?->galones ?? 0);
                                $factura = (string) ($row->fuelLog?->invoice?->numero_factura ?? $row->fuelLog?->invoice?->numero ?? '');
                                $packageCount = max(0, (int) ($row->cantidad_paquetes ?? 0));
                            @endphp
                            <tr>
                                <td>{{ optional($row->fecha)->format('d/m/y') ?? '' }}</td>
                                <td class="num">{{ $litros > 0 ? number_format($litros, 2) : '' }}</td>
                                <td>{{ $factura }}</td>
                                <td class="num">{{ $kmSalida !== null ? number_format($kmSalida, 2) : '' }}</td>
                                <td class="num">{{ $kmLlegada !== null ? number_format($kmLlegada, 2) : '' }}</td>
                                <td class="num">{{ $kmRecorrido !== null ? number_format($kmRecorrido, 2) : '0' }}</td>
                                <td>{{ (string) ($row->recorrido_inicio ?? '') }}</td>
                                <td>{{ (string) ($row->recorrido_destino ?? '') }}</td>
                                <td class="text-center">{{ $packageCount > 0 ? $packageCount : '' }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                        @for($index = 0; $index < $blankRows; $index++)
                            <tr>
                                <td>&nbsp;</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td class="num">0</td>
                                <td></td>
                                <td></td>
                                <td></td>
                                <td></td>
                            </tr>
                        @endfor
                    </tbody>
                </table>

                <div style="margin-top:10px; font-size:10px; font-weight:700; text-transform:uppercase;">
                    Llenado por el conductor designado
                </div>

                <table style="width:100%; border-collapse:collapse; margin-top:10px;">
                    <tr>
                        <td style="width:22%;"></td>
                        <td style="width:28%;">
                            <table style="width:100%; border-collapse:collapse;">
                                <tr>
                                    <td style="border:1px solid #000; background:#d2d8df; text-align:center; font-weight:700; font-size:10px; padding:6px;">
                                        Firma y sello del conductor designado
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border:1px solid #000; height:72px;"></td>
                                </tr>
                            </table>
                        </td>
                        <td style="width:6%;"></td>
                        <td style="width:28%;">
                            <table style="width:100%; border-collapse:collapse;">
                                <tr>
                                    <td style="border:1px solid #000; background:#d2d8df; text-align:center; font-weight:700; font-size:10px; padding:6px;">
                                        Firma y sello del inmediato superior
                                    </td>
                                </tr>
                                <tr>
                                    <td style="border:1px solid #000; height:72px;"></td>
                                </tr>
                            </table>
                        </td>
                        <td style="width:16%;"></td>
                    </tr>
                </table>
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
