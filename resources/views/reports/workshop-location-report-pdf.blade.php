<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Vehiculos en taller por ubicacion</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1, h2 { margin: 0; }
        h1 { font-size: 22px; color: #123c69; }
        h2 { font-size: 14px; margin-top: 16px; color: #0f172a; }
        .header { border-bottom: 3px solid #f3c316; padding-bottom: 10px; margin-bottom: 14px; }
        .meta { margin-top: 8px; color: #4b5563; }
        .group-title { background: #fef3c7; border-left: 5px solid #facc15; padding: 8px 10px; margin-top: 15px; font-weight: bold; }
        .muted { color: #6b7280; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 999px; color: #fff; font-size: 10px; font-weight: bold; background: #2563eb; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 7px; vertical-align: top; }
        th { background: #eff6ff; color: #123c69; text-align: left; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de vehiculos en taller por ubicacion</h1>
        <div class="meta">
            <strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}
            |
            <strong>Total en taller:</strong> {{ $workshops->count() }}
        </div>
    </div>

    @if($workshops->isEmpty())
        <p class="muted">No hay vehiculos actualmente en taller.</p>
    @else
        @foreach($groupedWorkshops as $workshopName => $items)
            @php($first = $items->first())
            <div class="group-title">
                {{ $workshopName }}
                <span class="muted">
                    |
                    Ubicacion: {{ $first?->service_location ?: ($first?->workshopCatalog?->location_label ?: 'Sin ubicacion') }}
                    |
                    Atencion: {{ $first?->workshopCatalog?->attention_hours ?: 'Sin horario' }}
                </span>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>OT</th>
                        <th>Vehiculo</th>
                        <th>Conductor</th>
                        <th>Estado</th>
                        <th>Ingreso</th>
                        <th>Hora atencion</th>
                        <th>Entrega estimada</th>
                        <th>Mantenimiento</th>
                        <th>Ubicacion actual</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $workshop)
                        <tr>
                            <td>{{ $workshop->order_number ?: 'Pendiente' }}</td>
                            <td>
                                {{ $workshop->vehicle?->placa ?? 'Sin placa' }}
                                <br>
                                <span class="muted">
                                    {{ $workshop->vehicle?->brand?->nombre ?? $workshop->vehicle?->marca ?? 'Vehiculo' }}
                                    {{ $workshop->vehicle?->modelo ? ' / ' . $workshop->vehicle?->modelo : '' }}
                                </span>
                            </td>
                            <td>{{ $workshop->driver?->nombre ?? 'Sin conductor' }}</td>
                            <td><span class="badge">{{ $workshop->estado }}</span></td>
                            <td>{{ optional($workshop->fecha_ingreso)->format('d/m/Y') ?: '-' }}</td>
                            <td>{{ optional($workshop->attention_started_at)->format('d/m/Y H:i') ?: 'Sin registrar' }}</td>
                            <td>{{ optional($workshop->fecha_prometida_entrega)->format('d/m/Y') ?: 'Pendiente' }}</td>
                            <td>
                                {{ $workshop->maintenanceAlert?->maintenanceType?->nombre ?? $workshop->maintenanceAppointment?->tipoMantenimiento?->nombre ?? 'Orden general' }}
                            </td>
                            <td>{{ $workshop->service_location ?: ($workshop->workshopCatalog?->location_label ?: 'Sin ubicacion') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
</body>
</html>
