<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de documentos de mantenimiento</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1f2937; }
        h1, h2 { margin: 0 0 8px; }
        .meta { margin-bottom: 18px; }
        .section { margin-top: 18px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; vertical-align: top; }
        th { background: #eff6ff; text-align: left; }
        .muted { color: #6b7280; }
    </style>
</head>
<body>
    <h1>Reporte de documentos de mantenimiento</h1>
    <div class="meta">
        <div><strong>Desde:</strong> {{ $from->format('d/m/Y') }}</div>
        <div><strong>Hasta:</strong> {{ $to->format('d/m/Y') }}</div>
        <div><strong>Vehiculo:</strong> {{ $vehicle?->placa ?? 'Todos' }}</div>
        <div><strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</div>
    </div>

    <div class="section">
        <h2>Registros de mantenimiento</h2>
        @if($logs->count())
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Vehiculo</th>
                        <th>Tipo</th>
                        <th>Costo</th>
                        <th>Descripcion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($logs as $log)
                        <tr>
                            <td>{{ optional($log->fecha)->format('d/m/Y') }}</td>
                            <td>{{ $log->vehicle?->placa ?? 'N/A' }}</td>
                            <td>{{ $log->tipo }}</td>
                            <td>BOB {{ number_format((float) $log->costo, 2) }}</td>
                            <td>{{ $log->descripcion ?: $log->observaciones ?: 'Sin detalle' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No hay registros en el rango solicitado.</div>
        @endif
    </div>

    <div class="section">
        <h2>Solicitudes y citas</h2>
        @if($appointments->count())
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Vehiculo</th>
                        <th>Conductor</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($appointments as $appointment)
                        <tr>
                            <td>{{ optional($appointment->fecha_programada)->format('d/m/Y H:i') }}</td>
                            <td>{{ $appointment->vehicle?->placa ?? 'N/A' }}</td>
                            <td>{{ $appointment->driver?->nombre ?? 'Sin conductor' }}</td>
                            <td>{{ $appointment->tipoMantenimiento?->nombre ?? 'Sin tipo' }}</td>
                            <td>{{ $appointment->estado }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No hay solicitudes o citas en el rango solicitado.</div>
        @endif
    </div>

    <div class="section">
        <h2>Alertas de mantenimiento</h2>
        @if($alerts->count())
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Vehiculo</th>
                        <th>Tipo</th>
                        <th>Estado</th>
                        <th>Mensaje</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($alerts as $alert)
                        <tr>
                            <td>{{ optional($alert->created_at)->format('d/m/Y H:i') }}</td>
                            <td>{{ $alert->vehicle?->placa ?? 'N/A' }}</td>
                            <td>{{ $alert->maintenanceType?->nombre ?? $alert->tipo }}</td>
                            <td>{{ $alert->status }}</td>
                            <td>{{ $alert->mensaje }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="muted">No hay alertas en el rango solicitado.</div>
        @endif
    </div>
</body>
</html>
