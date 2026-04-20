<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de asignaciones vehiculares</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1, h2, h3 { margin: 0; }
        h1 { font-size: 22px; color: #123c69; }
        h2 { font-size: 15px; margin-top: 18px; color: #0f172a; }
        h3 { font-size: 13px; margin-top: 12px; color: #1f2937; }
        .header { border-bottom: 3px solid #f3c316; padding-bottom: 10px; margin-bottom: 14px; }
        .meta { display: table; width: 100%; margin-top: 10px; }
        .meta-row { display: table-row; }
        .meta-cell { display: table-cell; padding: 2px 16px 2px 0; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 999px; color: #fff; font-size: 10px; font-weight: bold; }
        .badge-green { background: #16a34a; }
        .badge-red { background: #dc2626; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 7px; vertical-align: top; }
        th { background: #eff6ff; color: #123c69; text-align: left; font-size: 10px; text-transform: uppercase; }
        .summary { margin-top: 10px; padding: 8px 10px; background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 8px; }
        .group-title { background: #fefce8; border-left: 4px solid #facc15; padding: 7px 9px; margin-top: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de asignaciones vehiculares</h1>
        <div class="meta">
            <div class="meta-row">
                <div class="meta-cell"><strong>Desde:</strong> {{ $from->format('d/m/Y') }}</div>
                <div class="meta-cell"><strong>Hasta:</strong> {{ $to->format('d/m/Y') }}</div>
                <div class="meta-cell"><strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-cell"><strong>Agrupado por:</strong> {{ $groupBy === 'vehicle' ? 'Vehiculos' : 'Personal' }}</div>
                <div class="meta-cell"><strong>Vehiculo:</strong> {{ $vehicle?->placa ?? 'Todos' }}</div>
                <div class="meta-cell"><strong>Personal:</strong> {{ $driver?->nombre ?? 'Todos' }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-cell"><strong>Estado:</strong>
                    @if($status === 'active')
                        Solo activas
                    @elseif($status === 'inactive')
                        Solo inactivas
                    @else
                        Todas
                    @endif
                </div>
                <div class="meta-cell"><strong>Total:</strong> {{ $assignments->count() }} asignaciones</div>
                <div class="meta-cell"></div>
            </div>
        </div>
    </div>

    <div class="summary">
        Este reporte incluye asignaciones cuyo periodo se cruza con el rango seleccionado, aunque hayan iniciado antes o finalicen despues.
    </div>

    @if($assignments->isEmpty())
        <p class="muted">No hay asignaciones para los filtros seleccionados.</p>
    @else
        @foreach($groupedAssignments as $groupAssignments)
            @php($first = $groupAssignments->first())
            <div class="group-title">
                @if($groupBy === 'vehicle')
                    Vehiculo:
                    {{ $first?->vehicle?->placa ?? 'Sin placa' }}
                    -
                    {{ $first?->vehicle?->vehicleClass?->nombre ?? ($first?->vehicle?->marca ?: 'Vehiculo') }}
                    {{ $first?->vehicle?->modelo ? ' / Modelo ' . $first?->vehicle?->modelo : '' }}
                @else
                    Personal:
                    {{ $first?->driver?->nombre ?? 'Sin conductor' }}
                    {{ $first?->driver?->licencia ? ' / Lic. ' . $first?->driver?->licencia : '' }}
                @endif
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Conductor</th>
                        <th>Vehiculo</th>
                        <th>Tipo</th>
                        <th>Inicio</th>
                        <th>Fin</th>
                        <th>Estado</th>
                        <th>Observacion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groupAssignments as $assignment)
                        <tr>
                            <td>{{ $assignment->driver?->nombre ?? 'Sin conductor' }}</td>
                            <td>
                                {{ $assignment->vehicle?->placa ?? 'Sin placa' }}
                                <br>
                                <span class="muted">
                                    {{ $assignment->vehicle?->vehicleClass?->nombre ?? ($assignment->vehicle?->marca ?: 'Vehiculo') }}
                                    {{ $assignment->vehicle?->modelo ? ' / ' . $assignment->vehicle?->modelo : '' }}
                                </span>
                            </td>
                            <td>{{ $assignment->tipo_asignacion ?: 'Fijo' }}</td>
                            <td>{{ optional($assignment->fecha_inicio)->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ optional($assignment->fecha_fin)->format('d/m/Y') ?? 'Indefinido' }}</td>
                            <td>
                                @if($assignment->activo)
                                    <span class="badge badge-green">Activo</span>
                                @else
                                    <span class="badge badge-red">Inactivo</span>
                                @endif
                            </td>
                            <td>
                                @if($assignment->fecha_fin && $assignment->fecha_fin->lt($from))
                                    Fuera del rango actual, historico cerrado.
                                @elseif($assignment->fecha_inicio && $assignment->fecha_inicio->lt($from))
                                    Iniciado antes del rango.
                                @elseif(!$assignment->fecha_fin)
                                    Asignacion sin fecha fin.
                                @else
                                    Dentro del rango seleccionado.
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
</body>
</html>
