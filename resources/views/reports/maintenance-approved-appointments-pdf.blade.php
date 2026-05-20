<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de citas aprobadas</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1, h2 { margin: 0; }
        h1 { font-size: 22px; color: #123c69; }
        h2 { font-size: 14px; margin-top: 16px; color: #0f172a; }
        .header { border-bottom: 3px solid #f3c316; padding-bottom: 10px; margin-bottom: 14px; }
        .meta { display: table; width: 100%; margin-top: 10px; }
        .meta-row { display: table-row; }
        .meta-cell { display: table-cell; padding: 2px 16px 2px 0; }
        .summary { margin-top: 10px; padding: 8px 10px; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; }
        .muted { color: #6b7280; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 999px; color: #fff; font-size: 10px; font-weight: bold; }
        .badge-blue { background: #2563eb; }
        .group-title { background: #fefce8; border-left: 4px solid #facc15; padding: 7px 9px; margin-top: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 7px; vertical-align: top; }
        th { background: #f8fafc; color: #123c69; text-align: left; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de citas aprobadas</h1>
        <div class="meta">
            <div class="meta-row">
                <div class="meta-cell"><strong>Desde:</strong> {{ $from->format('d/m/Y') }}</div>
                <div class="meta-cell"><strong>Hasta:</strong> {{ $to->format('d/m/Y') }}</div>
                <div class="meta-cell"><strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-cell">
                    <strong>Dividido por:</strong>
                    @if($groupBy === 'vehicle')
                        Vehiculo
                    @elseif($groupBy === 'driver')
                        Personal
                    @else
                        Dia de aprobacion
                    @endif
                </div>
                <div class="meta-cell"><strong>Vehiculo:</strong> {{ $vehicle?->placa ?? 'Todos' }}</div>
                <div class="meta-cell"><strong>Personal:</strong> {{ $driver?->nombre ?? 'Todos' }}</div>
            </div>
            <div class="meta-row">
                <div class="meta-cell"><strong>Total aprobadas:</strong> {{ $appointments->count() }}</div>
                <div class="meta-cell"></div>
                <div class="meta-cell"></div>
            </div>
        </div>
    </div>

    <div class="summary">
        Se toma como fecha de aprobacion el campo aprobado_at. Para registros antiguos sin ese dato, se usa la ultima actualizacion del registro como respaldo.
    </div>

    @if($appointments->isEmpty())
        <p class="muted">No hay citas aprobadas para los filtros seleccionados.</p>
    @else
        @foreach($groupedAppointments as $groupAppointments)
            @php($first = $groupAppointments->first())
            @php($approvalDate = $first?->approved_at ?: $first?->updated_at)
            <div class="group-title">
                @if($groupBy === 'vehicle')
                    Vehiculo:
                    {{ $first?->vehicle?->placa ?? 'Sin placa' }}
                    -
                    {{ $first?->vehicle?->brand?->nombre ?? $first?->vehicle?->marca ?? 'Vehiculo' }}
                    {{ $first?->vehicle?->modelo ? ' / ' . $first?->vehicle?->modelo : '' }}
                @elseif($groupBy === 'driver')
                    Personal:
                    {{ $first?->driver?->nombre ?? 'Sin conductor' }}
                @else
                    Dia aprobado:
                    {{ optional($approvalDate)->format('d/m/Y') ?? '-' }}
                @endif
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Dia aprobado</th>
                        <th>Hora aprobada</th>
                        <th>Vehiculo</th>
                        <th>Conductor</th>
                        <th>Mantenimiento</th>
                        <th>Solicitud</th>
                        <th>Cita programada</th>
                        <th>Aprobado por</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($groupAppointments as $appointment)
                        @php($approvedAt = $appointment->approved_at ?: $appointment->updated_at)
                        <tr>
                            <td>{{ optional($approvedAt)->format('d/m/Y') ?? '-' }}</td>
                            <td>{{ optional($approvedAt)->format('H:i') ?? '-' }}</td>
                            <td>
                                {{ $appointment->vehicle?->placa ?? 'Sin placa' }}
                                <br>
                                <span class="muted">
                                    {{ $appointment->vehicle?->brand?->nombre ?? $appointment->vehicle?->marca ?? 'Vehiculo' }}
                                    {{ $appointment->vehicle?->modelo ? ' / ' . $appointment->vehicle?->modelo : '' }}
                                </span>
                            </td>
                            <td>{{ $appointment->driver?->nombre ?? 'Sin conductor' }}</td>
                            <td>
                                @if($appointment->es_accidente)
                                    Accidente
                                @else
                                    {{ $appointment->tipoMantenimiento?->nombre ?? 'Mantenimiento' }}
                                @endif
                            </td>
                            <td>{{ optional($appointment->solicitud_fecha ?? $appointment->created_at)->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ optional($appointment->fecha_programada)->format('d/m/Y H:i') ?? '-' }}</td>
                            <td>{{ $appointment->approvedBy?->name ?? 'Sistema / registro anterior' }}</td>
                            <td><span class="badge badge-blue">{{ $appointment->estado }}</span></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
</body>
</html>
