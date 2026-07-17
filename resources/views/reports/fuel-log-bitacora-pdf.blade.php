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
<<<<<<< Updated upstream
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
=======
>>>>>>> Stashed changes
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
            @php
                $chunkRows = collect($rowsChunk)->values();
                $blankRows = max(10 - $chunkRows->count(), 0);
                $driverNames = collect($group['drivers_used'] ?? [])
                    ->filter(fn ($name) => trim((string) $name) !== '')
                    ->implode(', ');
                $driverLabel = $driverNames !== ''
                    ? mb_strtoupper($driverNames)
                    : mb_strtoupper((string) ($group['driver']?->nombre ?? 'SIN CONDUCTOR DESIGNADO'));
                $vehicleLabel = trim((string) (($group['vehicle']?->brand?->nombre ?? '') . ' ' . ($group['vehicle']?->modelo ?? '')));
            @endphp
            <div class="page">
<<<<<<< Updated upstream
                <style>
                    .page {
                        height: 198mm;
                        overflow: hidden;
                        position: relative;
                    }
                    .official-wrap {
                        font-family: Arial, Helvetica, sans-serif;
                        color: #222;
                        height: 198mm;
                        position: relative;
                    }
                    .official-top { width: 100%; border-collapse: collapse; margin-bottom: 7px; }
                    .official-top td { vertical-align: middle; }
                    .official-brand-left { width: 28%; font-size: 8px; color: #777; line-height: 1.15; }
                    .official-brand-center { width: 44%; text-align: center; }
                    .official-brand-right { width: 28%; text-align: right; }
                    .official-brand-right img { max-width: 132px; max-height: 46px; }
                    .official-title { text-align: center; font-size: 12px; font-weight: 700; letter-spacing: 0.2px; margin: 6px 0 8px; text-transform: uppercase; }
                    .official-meta { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
                    .official-meta td { border: 1px solid #8c8c8c; padding: 3px 5px; height: 19px; vertical-align: top; }
                    .official-meta .field-label { display: block; font-size: 7px; color: #6d6d6d; margin-bottom: 1px; text-transform: uppercase; }
                    .official-meta .field-value { font-size: 8px; font-weight: 700; text-transform: uppercase; }
                    .official-table { width: 100%; border-collapse: collapse; table-layout: fixed; }
                    .official-table th,
                    .official-table td {
                        border: 1px solid #8c8c8c;
                        padding: 2px 3px;
                        vertical-align: middle;
                        overflow: hidden;
                    }
                    .official-table thead th { background: #d9dcdf; text-align: center; text-transform: uppercase; font-size: 7px; line-height: 1.05; }
                    .official-table thead .subhead th { background: #eceeef; font-size: 7px; }
                    .official-table tbody td {
                        height: 9.2mm;
                        max-height: 9.2mm;
                        font-size: 7px;
                        line-height: 1.08;
                    }
                    .w-date { width: 7.5%; }
                    .w-liters { width: 7%; }
                    .w-invoice { width: 7%; }
                    .w-km { width: 11%; }
                    .w-total { width: 9%; }
                    .w-route { width: 15%; }
                    .w-guides { width: 10.5%; }
                    .w-sign { width: 7%; }
                    .route-cell {
                        font-size: 6px;
                        line-height: 1.05;
                    }
                    .official-note {
                        margin-top: 7px;
                        font-size: 8px;
                        font-weight: 700;
                        text-transform: uppercase;
                    }
                    .official-signatures {
                        width: 62%;
                        margin: 8px auto 0;
                        border-collapse: separate;
                        border-spacing: 48px 0;
                    }
                    .official-signatures td { width: 50%; vertical-align: top; }
                    .signature-box { border: 1px solid #8c8c8c; height: 27mm; }
                    .signature-box-title { background: #d9dcdf; border-bottom: 1px solid #8c8c8c; text-align: center; font-size: 7px; font-weight: 700; text-transform: uppercase; padding: 4px 6px; height: 9mm; line-height: 1.15; }
                    .signature-box-body { height: 18mm; }
                    .text-center { text-align: center; }
                    .text-right { text-align: right; }
                </style>

                <div class="official-wrap">
                    <table class="official-top">
                        <tr>
                            <td class="official-brand-left">
                                <div>ESTADO PLURINACIONAL</div>
                                <div>OBRAS PUBLICAS,</div>
                                <div>SERVICIOS Y VIVIENDA</div>
                            </td>
                            <td class="official-brand-center"></td>
                            <td class="official-brand-right">
                                @if($logoData)
                                    <img src="{{ $logoData }}" alt="Correos de Bolivia">
                                @else
                                    <div style="font-size: 18px; font-weight: 700; color: #777;">CORREOS DE BOLIVIA</div>
                                @endif
                            </td>
                        </tr>
                    </table>

                    <div class="official-title">Formulario de bitacora de control para provision de combustible</div>

                    <table class="official-meta">
                        <tr>
                            <td style="width: 40%;">
                                <span class="field-label">Conductor:</span>
                                <div class="field-value">{{ $showDriver ? $driverLabel : '' }}</div>
                            </td>
                            <td style="width: 20%;">
                                <span class="field-label">Placa:</span>
                                <div class="field-value">{{ $showPlaca ? ($group['vehicle']?->placa ?? '') : '' }}</div>
                            </td>
                            <td style="width: 40%;">
                                <span class="field-label">Vehiculo:</span>
                                <div class="field-value">{{ $showVehiculo ? ($vehicleLabel !== '' ? $vehicleLabel : '-') : '' }}</div>
                            </td>
                        </tr>
                    </table>

                    <table class="official-table">
                        <thead>
                            <tr>
                                <th class="w-date" rowspan="2">Fecha</th>
                                <th class="w-liters" rowspan="2">Cantidad de litros</th>
                                <th class="w-invoice" rowspan="2">Numero de factura</th>
                                <th class="w-km" colspan="2">Kilometraje</th>
                                <th class="w-total" rowspan="2">Total recorrido (Km)</th>
                                <th class="w-route" colspan="2">Recorrido</th>
                                <th class="w-guides" rowspan="2">Cantidad de guias llevadas y/o recogidas</th>
                                <th class="w-sign" rowspan="2">Firma</th>
                            </tr>
                            <tr class="subhead">
                                <th>Salida</th>
                                <th>Llegada</th>
                                <th>Inicio</th>
                                <th>Destino</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($chunkRows as $row)
                                @php
                                    $kmSalida = $row->kilometraje_salida !== null ? (float) $row->kilometraje_salida : null;
                                    $kmLlegada = $row->kilometraje_llegada !== null ? (float) $row->kilometraje_llegada : null;
                                    $kmRecorrido = $row->kilometraje_recorrido !== null
                                        ? (float) $row->kilometraje_recorrido
                                        : (($kmSalida !== null && $kmLlegada !== null) ? max(0, $kmLlegada - $kmSalida) : null);
                                    $litros = $row->fuelLog?->cantidad ?? $row->fuelLog?->galones;
                                    $invoiceNumber = trim((string) ($row->fuelLog?->invoice?->numero_factura ?? $row->fuelLog?->invoice?->numero ?? ''));
                                    $packageCount = $row->cantidad_paquetes !== null ? (int) $row->cantidad_paquetes : null;
                                    $hasSignature = trim((string) ($row->firma_digital ?? '')) !== '';
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $showFecha ? (optional($row->fecha)->format('d/m/y') ?? '-') : '' }}</td>
                                    <td class="text-right">{{ $showCombustible && $litros !== null ? number_format((float) $litros, 2) : '' }}</td>
                                    <td class="text-center">{{ $showCombustible ? ($invoiceNumber !== '' ? $invoiceNumber : '') : '' }}</td>
                                    <td class="text-right">{{ $showKmSalida && $kmSalida !== null ? number_format($kmSalida, 2) : '' }}</td>
                                    <td class="text-right">{{ $showKmLlegada && $kmLlegada !== null ? number_format($kmLlegada, 2) : '' }}</td>
                                    <td class="text-right">{{ $showKmRecorrido && $kmRecorrido !== null ? number_format($kmRecorrido, 2) : '0' }}</td>
                                    <td class="route-cell">{{ $showRecorrido ? (trim((string) ($row->recorrido_inicio ?? '')) !== '' ? $row->recorrido_inicio : '') : '' }}</td>
                                    <td class="route-cell">{{ $showRecorrido ? (trim((string) ($row->recorrido_destino ?? '')) !== '' ? $row->recorrido_destino : '') : '' }}</td>
                                    <td class="text-center">{{ $packageCount !== null ? $packageCount : '' }}</td>
                                    <td class="text-center">{{ $hasSignature ? 'SI' : '' }}</td>
                                </tr>
                            @endforeach

                            @for($i = 0; $i < $blankRows; $i++)
                                <tr>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td class="text-center">0</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                    <td>&nbsp;</td>
                                </tr>
                            @endfor
                        </tbody>
                    </table>

                    <div class="official-note">Llenado por el conductor designado</div>

                    <table class="official-signatures">
                        <tr>
                            <td>
                                <div class="signature-box">
                                    <div class="signature-box-title">Firma y sello del conductor designado</div>
                                    <div class="signature-box-body"></div>
                                </div>
                            </td>
                            <td>
                                <div class="signature-box">
                                    <div class="signature-box-title">Firma y sello del inmediato superior</div>
                                    <div class="signature-box-body"></div>
                                </div>
                            </td>
                        </tr>
                    </table>
                </div>
=======
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
>>>>>>> Stashed changes
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
