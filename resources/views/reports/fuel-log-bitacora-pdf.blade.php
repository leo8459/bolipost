<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Bitacora de Control para Provision de Combustible</title>
    <style>
        @page { margin: 8mm; }
        body { font-family: Arial, sans-serif; font-size: 11px; color: #111; margin: 0; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .title { text-align: center; font-weight: 700; font-size: 14px; margin-bottom: 2px; text-transform: uppercase; }
        .subtitle { text-align: center; font-weight: 700; font-size: 18px; margin: 2px 0 6px; }
        .meta { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        .meta td { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .meta-label { font-weight: 700; display: block; font-size: 10px; margin-bottom: 2px; }
        .meta-value { font-weight: 700; font-size: 12px; text-transform: uppercase; }
        .table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .table th, .table td { border: 1px solid #000; padding: 4px; }
        .table th { font-weight: 700; text-align: center; }
        .table thead th { background: #d2d8df; font-size: 10px; text-transform: uppercase; }
        .table .subhead th { background: #e4e8ec; font-size: 10px; }
        .num { text-align: right; }
        .small { font-size: 10px; color: #333; }
        .filters { margin-bottom: 6px; font-size: 10px; color: #333; }
        .foot-label { margin-top: 8px; font-size: 12px; font-weight: 700; text-transform: uppercase; }
        .signatures { width: 70%; border-collapse: collapse; margin-top: 6px; }
        .signatures td { border: 1px solid #000; height: 72px; width: 50%; vertical-align: top; }
        .signatures .head { background: #d2d8df; font-size: 11px; font-weight: 700; text-align: center; text-transform: uppercase; height: 26px; }
        .signatures .body { padding: 8px; }
        .sign-name { margin-top: 28px; border-top: 1px solid #000; padding-top: 3px; font-size: 11px; font-weight: 700; text-align: center; }
    </style>
</head>
<body>
@forelse($groups as $group)
    @php
        $chunks = ($group['row_chunks'] ?? collect())->count() > 0 ? $group['row_chunks'] : collect([$group['rows'] ?? collect()]);
    @endphp
    @foreach($chunks as $chunkIndex => $rowsChunk)
    <div class="page">
        <div class="title">ANEXO 2</div>
        <div class="subtitle">FORMULARIO DE BITACORA DE CONTROL PARA PROVISION DE COMBUSTIBLE</div>

        <div class="filters">
            Generado: {{ $generatedAt->format('d/m/Y H:i') }}
            | Desde: {{ $fechaDesde ?: 'Todos' }}
            | Hasta: {{ $fechaHasta ?: 'Todos' }}
            | Placa filtro: {{ $placaFiltro !== '' ? $placaFiltro : 'Todas' }}
        </div>

        <table class="meta">
            <tr>
                <td>
                    <span class="meta-label">{{ ($group['drivers_used']?->count() ?? 0) > 1 ? 'CONDUCTORES' : 'CONDUCTOR' }}</span>
                    <div class="meta-value">
                        @if(($group['drivers_used']?->count() ?? 0) > 0)
                            {{ strtoupper($group['drivers_used']->implode(' / ')) }}
                        @else
                            {{ strtoupper((string) ($group['driver']?->nombre ?? 'SIN CONDUCTOR DESIGNADO')) }}
                        @endif
                    </div>
                </td>
                <td>
                    <span class="meta-label">PLACA</span>
                    <div class="meta-value">{{ $group['vehicle']?->placa ?? 'SIN PLACA' }}</div>
                </td>
                <td>
                    <span class="meta-label">VEHICULO</span>
                    <div class="meta-value">{{ trim(($group['vehicle']?->brand?->nombre ?? '') . ' ' . ($group['vehicle']?->modelo ?? '')) ?: '-' }}</div>
                </td>
            </tr>
        </table>

        <table class="table">
            <thead>
                <tr>
                    <th style="width: 9%;">Fecha</th>
                    <th colspan="2" style="width: 20%;">Kilometraje</th>
                    <th style="width: 10%;">Total Recorrido (Km)</th>
                    <th colspan="2" style="width: 28%;">Recorrido</th>
                    <th style="width: 9%;">Conductor</th>
                    <th style="width: 11%;">Abastecimiento de Combustible</th>
                    <th style="width: 9%;">Litros</th>
                </tr>
                <tr class="subhead">
                    <th></th>
                    <th>Salida</th>
                    <th>Llegada</th>
                    <th></th>
                    <th>Inicio</th>
                    <th>Destino</th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($rowsChunk as $row)
                    @php
                        $kmSalida = $row->kilometraje_salida !== null ? (float) $row->kilometraje_salida : null;
                        $kmLlegada = $row->kilometraje_llegada !== null ? (float) $row->kilometraje_llegada : null;
                        $recorrido = ($kmSalida !== null && $kmLlegada !== null) ? max(0, $kmLlegada - $kmSalida) : null;
                        $litros = (float) ($row->fuelLog?->galones ?? 0);
                    @endphp
                    <tr>
                        <td>{{ optional($row->fecha)->format('d/m/y') ?? '-' }}</td>
                        <td class="num">{{ $kmSalida !== null ? number_format($kmSalida, 2) : '-' }}</td>
                        <td class="num">{{ $kmLlegada !== null ? number_format($kmLlegada, 2) : '-' }}</td>
                        <td class="num">{{ $recorrido !== null ? number_format($recorrido, 2) : '-' }}</td>
                        <td>{{ $row->recorrido_inicio ?? '-' }}</td>
                        <td>{{ $row->recorrido_destino ?? '-' }}</td>
                        <td>{{ strtoupper((string) ($row->driver?->nombre ?? '-')) }}</td>
                        <td>{{ $row->abastecimiento_combustible ? 'Si' : 'No' }}</td>
                        <td class="num">{{ $litros > 0 ? number_format($litros, 2) : '-' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="foot-label">Llenado por el conductor designado</div>
        <table class="signatures">
            <tr>
                <td class="head">Firma y sello del conductor designado</td>
                <td class="head">Firma y sello del inmediato superior</td>
            </tr>
            <tr>
                <td class="body">
                    @if(($group['drivers_used']?->count() ?? 0) > 0)
                        <div class="small" style="margin-top: 8px;">Conductores que usaron el vehiculo:</div>
                        <div class="small" style="margin-top: 3px;">
                            {{ strtoupper($group['drivers_used']->implode(' | ')) }}
                        </div>
                    @endif
                    <div class="sign-name">&nbsp;</div>
                </td>
                <td class="body">
                    <div class="sign-name">&nbsp;</div>
                </td>
            </tr>
        </table>
        <div class="small" style="margin-top: 6px;">Documento generado por el sistema de bitacora.</div>
    </div>
    @endforeach
@empty
    <div class="page">
        <div class="title">ANEXO 2</div>
        <div class="subtitle">FORMULARIO DE BITACORA DE CONTROL PARA PROVISION DE COMBUSTIBLE</div>
        <p>No existen registros para los filtros seleccionados.</p>
    </div>
@endforelse
</body>
</html>
