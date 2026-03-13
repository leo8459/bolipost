<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Tarifario</title>
    <style>
        @page {
            margin: 22px 24px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #1f2937;
        }

        .header {
            border-bottom: 2px solid #20539A;
            padding-bottom: 10px;
            margin-bottom: 16px;
        }

        .title {
            font-size: 20px;
            font-weight: bold;
            color: #20539A;
            margin: 0 0 6px;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
        }

        .meta td {
            padding: 2px 0;
            vertical-align: top;
        }

        .summary {
            margin: 10px 0 14px;
            padding: 8px 10px;
            background: #f3f6fb;
            border: 1px solid #d7e1f1;
        }

        .service-block {
            margin-bottom: 18px;
        }

        .service-title {
            background: #20539A;
            color: #ffffff;
            padding: 7px 10px;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .tarifario-table th,
        .tarifario-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 7px;
        }

        .tarifario-table th {
            background: #e8eef8;
            color: #1e3a6d;
            font-weight: bold;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .muted {
            color: #6b7280;
        }

        .page-break {
            page-break-after: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Reporte de Tarifario</div>
        <table class="meta">
            <tr>
                <td><strong>Fecha de generacion:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</td>
                <td class="text-right"><strong>Total registros:</strong> {{ $totalTarifarios }}</td>
            </tr>
        </table>
    </div>

    <div class="summary">
        El reporte se encuentra agrupado por servicio. Cada bloque muestra los rangos de peso registrados y su precio actual.
    </div>

    @foreach ($groupedTarifarios as $serviceName => $items)
        <div class="service-block">
            <div class="service-title">{{ $serviceName }}</div>
            <table class="tarifario-table">
                <thead>
                    <tr>
                        <th style="width: 14%;">Peso inicial</th>
                        <th style="width: 14%;">Peso final</th>
                        <th style="width: 22%;">Rango</th>
                        <th style="width: 16%;">Precio</th>
                        <th style="width: 34%;">Observacion</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ number_format((float) optional($item->peso)->peso_inicial, 3, '.', ',') }}</td>
                            <td>{{ number_format((float) optional($item->peso)->peso_final, 3, '.', ',') }}</td>
                            <td>
                                {{ number_format((float) optional($item->peso)->peso_inicial, 3, '.', ',') }}
                                -
                                {{ number_format((float) optional($item->peso)->peso_final, 3, '.', ',') }} kg
                            </td>
                            <td class="text-right">{{ number_format((float) $item->precio, 2, '.', ',') }}</td>
                            <td>{{ $item->observacion ?: 'Sin observacion' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="muted" style="margin-top:6px;">
                Total del servicio: {{ $items->count() }} registro(s)
            </div>
        </div>

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @endforeach
</body>
</html>
