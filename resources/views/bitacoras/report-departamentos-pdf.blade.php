<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de bitacoras por departamento</title>
    <style>
        @page { margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #14213d; }
        .title { text-align: center; font-size: 18px; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
        .subtitle { text-align: center; font-size: 11px; color: #475569; margin-bottom: 10px; }
        .filters { margin-bottom: 10px; padding: 8px; border: 1px solid #cbd5e1; background: #f8fafc; }
        .metrics { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .metrics td { width: 25%; border: 1px solid #cbd5e1; padding: 8px; vertical-align: top; }
        .metric-label { font-size: 9px; text-transform: uppercase; color: #64748b; font-weight: 700; margin-bottom: 4px; }
        .metric-value { font-size: 15px; font-weight: 800; color: #1d4ed8; }
        .section-title { font-size: 12px; font-weight: 800; margin: 12px 0 6px; color: #1e3a8a; }
        table.sheet { width: 100%; border-collapse: collapse; margin-bottom: 12px; }
        .sheet th, .sheet td { border: 1px solid #334155; padding: 5px; }
        .sheet th { background: #dbeafe; text-transform: uppercase; font-size: 9px; }
        .right { text-align: right; }
        .muted { color: #64748b; }
    </style>
</head>
<body>
    <div class="title">Reporte de bitacoras por departamento</div>
    <div class="subtitle">Resumen profesional por origen, destino y ruta con sumado de precio total</div>

    <div class="filters">
        <strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}
        | <strong>Busqueda:</strong> {{ $filters['q'] !== '' ? $filters['q'] : 'Todas' }}
        | <strong>Regional:</strong> {{ $filters['regional'] !== '' ? $filters['regional'] : 'Todas' }}
        | <strong>Usuario:</strong> {{ ($filters['user'] ?? '') !== '' ? $filters['user'] : 'Todos' }}
        | <strong>Cod especial:</strong> {{ $filters['codEspecial'] !== '' ? $filters['codEspecial'] : 'Todos' }}
        | <strong>Provincia:</strong> {{ $filters['provincia'] !== '' ? $filters['provincia'] : 'Todas' }}
        | <strong>Origen CN-33:</strong> {{ $filters['origenCn33'] !== '' ? $filters['origenCn33'] : 'Todos' }}
    </div>

    <table class="metrics">
        <tr>
            <td>
                <div class="metric-label">Precio total acumulado</div>
                <div class="metric-value">Bs {{ number_format((float) data_get($reportTotals, 'total_precio', 0), 2) }}</div>
            </td>
            <td>
                <div class="metric-label">Registros agrupados</div>
                <div class="metric-value">{{ number_format((int) data_get($reportTotals, 'total_registros', 0)) }}</div>
            </td>
            <td>
                <div class="metric-label">Departamentos origen</div>
                <div class="metric-value">{{ number_format((int) data_get($reportTotals, 'origenes', 0)) }}</div>
            </td>
            <td>
                <div class="metric-label">Departamentos destino</div>
                <div class="metric-value">{{ number_format((int) data_get($reportTotals, 'destinos', 0)) }}</div>
            </td>
            <td>
                <div class="metric-label">Transportadoras</div>
                <div class="metric-value">{{ number_format((int) data_get($reportTotals, 'transportadoras', 0)) }}</div>
            </td>
        </tr>
    </table>

    <div class="section-title">Ranking de transportadoras</div>
    <table class="sheet">
        <thead>
            <tr>
                <th>Transportadora</th>
                <th class="right">Envios</th>
                <th class="right">Precio total</th>
                <th class="right">Peso total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportByTransportadora as $row)
                <tr>
                    <td>{{ strtoupper((string) $row->transportadora) }}</td>
                    <td class="right">{{ number_format((int) $row->total_registros) }}</td>
                    <td class="right">Bs {{ number_format((float) $row->total_precio, 2) }}</td>
                    <td class="right">{{ number_format((float) $row->total_peso, 3) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">No hay datos para mostrar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Totales por origen</div>
    <table class="sheet">
        <thead>
            <tr>
                <th>Origen</th>
                <th class="right">Registros</th>
                <th class="right">Precio total</th>
                <th class="right">Peso total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportByOrigin as $row)
                <tr>
                    <td>{{ $row->departamento }}</td>
                    <td class="right">{{ number_format((int) $row->total_registros) }}</td>
                    <td class="right">Bs {{ number_format((float) $row->total_precio, 2) }}</td>
                    <td class="right">{{ number_format((float) $row->total_peso, 3) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">No hay datos para mostrar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Totales por destino</div>
    <table class="sheet">
        <thead>
            <tr>
                <th>Destino</th>
                <th class="right">Registros</th>
                <th class="right">Precio total</th>
                <th class="right">Peso total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportByDestination as $row)
                <tr>
                    <td>{{ $row->departamento }}</td>
                    <td class="right">{{ number_format((int) $row->total_registros) }}</td>
                    <td class="right">Bs {{ number_format((float) $row->total_precio, 2) }}</td>
                    <td class="right">{{ number_format((float) $row->total_peso, 3) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" class="muted">No hay datos para mostrar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Cruce origen y destino</div>
    <table class="sheet">
        <thead>
            <tr>
                <th>Origen</th>
                <th>Destino</th>
                <th class="right">Registros</th>
                <th class="right">Precio total</th>
                <th class="right">Peso total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($reportRows as $row)
                <tr>
                    <td>{{ $row->origen_departamento }}</td>
                    <td>{{ $row->destino_departamento }}</td>
                    <td class="right">{{ number_format((int) $row->total_registros) }}</td>
                    <td class="right">Bs {{ number_format((float) $row->total_precio, 2) }}</td>
                    <td class="right">{{ number_format((float) $row->total_peso, 3) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="muted">No hay datos para mostrar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
