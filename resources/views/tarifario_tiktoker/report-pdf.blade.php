<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Tarifario Tiktoker</title>
    <style>
        @page {
            margin: 20px 24px;
        }

        body {
            font-family: Verdana, DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        .header {
            border-bottom: 2px solid #20539A;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }

        .title {
            font-size: 18px;
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

        .department-block {
            margin-bottom: 16px;
        }

        .department-title {
            background: #20539A;
            color: #ffffff;
            padding: 7px 10px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #cbd5e1;
            padding: 6px 7px;
        }

        .report-table th {
            background: #e8eef8;
            color: #1e3a6d;
            font-weight: bold;
            text-align: left;
        }

        .head-note {
            display: block;
            font-size: 9px;
            font-weight: normal;
            color: #4b5563;
            margin-top: 2px;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
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
    @php
        $regionalNameMap = [
            'PANDO' => 'COBIJA',
            'BENI' => 'TRINIDAD',
            'CHUQUISACA' => 'SUCRE',
        ];
        $regionalLabel = fn ($name) => $regionalNameMap[strtoupper(trim((string) $name))] ?? strtoupper(trim((string) $name));
    @endphp
    <div class="header">
        <div class="title">Reporte Tarifario Tiktoker</div>
        <table class="meta">
            <tr>
                <td><strong>Fecha de generacion:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</td>
                <td class="text-right"><strong>Total registros:</strong> {{ $totalTarifas }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Filtros aplicados:</strong> {{ $filters }}</td>
            </tr>
        </table>
    </div>

    <div class="summary">
        El reporte esta dividido por departamento de origen. En cada bloque se listan los destinos y tarifas vigentes para facilitar su revision y distribucion.
    </div>

    @forelse ($groupedTarifas as $department => $items)
        <div class="department-block">
            <div class="department-title">{{ $department }}</div>
            <table class="report-table">
                <thead>
                    <tr>
                        <th style="width: 20%;">Destino</th>
                        <th style="width: 20%;">Servicio extra</th>
                        <th style="width: 11%;">Peso 1 <span class="head-note">hasta 2 kg</span></th>
                        <th style="width: 11%;">Peso 2 <span class="head-note">hasta 5 kg</span></th>
                        <th style="width: 10%;">Peso 3 <span class="head-note">opcional</span></th>
                        <th style="width: 11%;">Peso extra <span class="head-note">+ de 5 kg</span></th>
                        <th style="width: 10%;">Entrega</th>
                        <th style="width: 7%;">Referencia <span class="head-note">5 kg + 1 kg extra</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td>{{ $regionalLabel(optional($item->destino)->nombre_destino) }}</td>
                            <td>{{ $item->servicioExtra?->nombre ?? 'General' }}</td>
                            <td class="text-right">{{ number_format((float) $item->peso1, 2, '.', ',') }}</td>
                            <td class="text-right">{{ number_format((float) $item->peso2, 2, '.', ',') }}</td>
                            <td class="text-right">{{ $item->peso3 !== null ? number_format((float) $item->peso3, 2, '.', ',') : '-' }}</td>
                            <td class="text-right">{{ number_format((float) $item->peso_extra, 2, '.', ',') }}</td>
                            <td class="text-center">{{ (int) $item->tiempo_entrega }} h</td>
                            <td class="text-right">{{ number_format((float) $item->peso2 + (float) $item->peso_extra, 2, '.', ',') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="muted" style="margin-top:6px;">
                Total del departamento: {{ $items->count() }} registro(s)
            </div>
        </div>

        @if (! $loop->last)
            <div class="page-break"></div>
        @endif
    @empty
        <div class="summary">
            No se encontraron registros para los filtros seleccionados.
        </div>
    @endforelse
</body>
</html>
