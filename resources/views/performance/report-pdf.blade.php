<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Performance</title>
    <style>
        @page { margin: 14px; }
        body {
            font-family: Verdana, DejaVu Sans, sans-serif;
            font-size: 8.4px;
            color: #1f2937;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            color: #0f4c81;
            margin-bottom: 4px;
        }
        .meta {
            font-size: 8.5px;
            color: #4b5563;
            margin-bottom: 10px;
        }
        .filters {
            margin-bottom: 10px;
            border: 1px solid #cbd5e1;
            padding: 8px;
            background: #f8fbff;
        }
        .filters-row {
            margin-bottom: 3px;
        }
        .kpis {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }
        .kpis td {
            border: 1px solid #cbd5e1;
            padding: 6px;
            text-align: center;
        }
        .kpis .label {
            font-size: 7px;
            text-transform: uppercase;
            color: #64748b;
        }
        .kpis .value {
            margin-top: 2px;
            font-size: 11px;
            font-weight: 700;
            color: #0f172a;
        }
        .section-title {
            font-size: 10px;
            font-weight: 700;
            color: #0f4c81;
            margin: 8px 0 5px;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
        }
        .report th,
        .report td {
            border: 1px solid #d5dde8;
            padding: 4px;
        }
        .report th {
            background: #eaf2fb;
            color: #163b6d;
            font-size: 7px;
            text-transform: uppercase;
        }
        .report td {
            font-size: 7.4px;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="title">Reporte Performance</div>
    <div class="meta">Generado el {{ $generatedAt->format('d/m/Y H:i') }}</div>

    <div class="filters">
        @foreach($filterSummary as $label => $value)
            <div class="filters-row"><strong>{{ $label }}:</strong> {{ $value }}</div>
        @endforeach
    </div>

    <table class="kpis">
        <tr>
            <td><div class="label">Registros</div><div class="value">{{ number_format($summary['total_registros']) }}</div></td>
            <td><div class="label">Origenes</div><div class="value">{{ number_format($summary['origenes']) }}</div></td>
            <td><div class="label">Destinos</div><div class="value">{{ number_format($summary['destinos']) }}</div></td>
            <td><div class="label">Eventos</div><div class="value">{{ number_format($summary['eventos']) }}</div></td>
        </tr>
    </table>

    <div class="section-title">Tabla consolidada</div>
    <table class="report">
        <thead>
            <tr>
                <th>Origen</th>
                <th>Destino</th>
                <th>Año</th>
                <th>Mes</th>
                @foreach($eventColumns as $eventColumn)
                    <th>{{ $eventColumn }}</th>
                @endforeach
                <th>Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($matrixRows as $matrixRow)
                <tr>
                    <td>{{ $matrixRow['origen'] }}</td>
                    <td>{{ $matrixRow['destino'] }}</td>
                    <td class="center">{{ $matrixRow['anio'] }}</td>
                    <td class="center">{{ $matrixRow['mes_label'] }}</td>
                    @foreach($eventColumns as $eventColumn)
                        <td class="right">{{ (int) ($matrixRow['counts'][$eventColumn] ?? 0) }}</td>
                    @endforeach
                    <td class="right"><strong>{{ (int) $matrixRow['total'] }}</strong></td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ 5 + count($eventColumns) }}" class="center">No hay resultados.</td>
                </tr>
            @endforelse
        </tbody>
        @if(count($matrixRows) > 0)
            <tfoot>
                <tr>
                    <th colspan="4" class="right">Totales</th>
                    @foreach($eventColumns as $eventColumn)
                        <th class="right">{{ (int) ($matrixTotals['events'][$eventColumn] ?? 0) }}</th>
                    @endforeach
                    <th class="right">{{ (int) ($matrixTotals['grand_total'] ?? 0) }}</th>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
