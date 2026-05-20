<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de mantenimiento</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            color: #1f2937;
            font-size: 12px;
        }
        .header {
            border-bottom: 2px solid #1d4ed8;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .title {
            font-size: 20px;
            font-weight: bold;
            color: #123f91;
            margin: 0 0 6px;
        }
        .subtitle {
            color: #4b5563;
            margin: 0;
        }
        .meta-grid {
            width: 100%;
            margin-bottom: 18px;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        .meta-label {
            width: 140px;
            color: #6b7280;
            font-weight: bold;
            vertical-align: top;
        }
        .meta-value {
            color: #111827;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
        }
        table.report th,
        table.report td {
            border: 1px solid #d1d5db;
            padding: 8px;
            vertical-align: top;
        }
        table.report th {
            background: #eff6ff;
            color: #123f91;
            text-align: left;
        }
        .empty {
            margin-top: 16px;
            padding: 12px;
            border: 1px dashed #cbd5e1;
            background: #f8fafc;
            color: #475569;
        }
        .footer {
            margin-top: 18px;
            font-size: 11px;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Historial de mantenimiento del vehiculo</p>
        <p class="subtitle">Reporte consolidado de mantenimientos registrados.</p>
    </div>

    <table class="meta-grid">
        <tr>
            <td class="meta-label">Placa</td>
            <td class="meta-value">{{ $vehicle->placa ?? '-' }}</td>
            <td class="meta-label">Marca</td>
            <td class="meta-value">{{ $vehicle->brand?->nombre ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Modelo</td>
            <td class="meta-value">{{ $vehicle->modelo ?? '-' }}</td>
            <td class="meta-label">Clase</td>
            <td class="meta-value">{{ $vehicle->vehicleClass?->nombre ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">KM actual</td>
            <td class="meta-value">{{ number_format((float) ($vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje ?? 0), 2) }}</td>
            <td class="meta-label">Generado</td>
            <td class="meta-value">{{ optional($generatedAt)->format('d/m/Y H:i') ?? '-' }}</td>
        </tr>
        <tr>
            <td class="meta-label">Generado por</td>
            <td class="meta-value" colspan="3">{{ $generatedBy?->name ?? 'Sistema' }}</td>
        </tr>
    </table>

    @if($maintenanceLogs->count())
        <table class="report">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Fecha</th>
                    <th>Mantenimiento</th>
                    <th>Taller</th>
                    <th>Costo</th>
                    <th>Kilometraje</th>
                    <th>Proximo KM</th>
                    <th>Observaciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($maintenanceLogs as $index => $log)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ optional($log->fecha)->format('d/m/Y') ?? '-' }}</td>
                        <td>{{ $log->maintenanceType?->nombre ?? $log->tipo ?? '-' }}</td>
                        <td>{{ $log->taller ?? '-' }}</td>
                        <td>Bs {{ number_format((float) ($log->costo ?? 0), 2) }}</td>
                        <td>{{ $log->kilometraje !== null ? number_format((float) $log->kilometraje, 2) : '-' }}</td>
                        <td>{{ $log->proximo_kilometraje !== null ? number_format((float) $log->proximo_kilometraje, 2) : '-' }}</td>
                        <td>{{ $log->observaciones ?: ($log->descripcion ?: '-') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div class="empty">
            Este vehiculo no tiene mantenimientos registrados todavia.
        </div>
    @endif

    <div class="footer">
        TrackingBO / Bolipost
    </div>
</body>
</html>
