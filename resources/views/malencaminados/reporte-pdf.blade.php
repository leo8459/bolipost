<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Malencaminados</title>
    <style>
        @page { margin: 16px; }
        body { font-family: Verdana, DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
        .header { margin-bottom: 12px; }
        .title { font-size: 18px; font-weight: 700; color: #1d4d92; }
        .meta { font-size: 10px; color: #4b5563; margin-top: 4px; }
        .kpi-wrap { width: 100%; margin: 10px 0 14px 0; }
        .kpi { width: 32%; display: inline-block; margin-right: 1.2%; background: #f3f7ff; border: 1px solid #d7e4ff; border-radius: 6px; padding: 8px; vertical-align: top; }
        .kpi:last-child { margin-right: 0; }
        .kpi .label { font-size: 9px; color: #4b5563; }
        .kpi .value { font-size: 12px; font-weight: 700; color: #0f2f63; margin-top: 2px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 5px; text-align: left; }
        th { background: #eef2ff; color: #1e3a8a; font-weight: 700; }
        .section-title { margin-top: 10px; font-size: 12px; font-weight: 700; color: #1f2937; }
        .small { font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Reporte de Malencaminados</div>
        <div class="meta">
            Generado: {{ $generadoEn->format('d/m/Y H:i') }} |
            Rango: {{ $fechaInicio }} a {{ $fechaFin }} |
            Departamento: {{ $departamento }}
        </div>
    </div>

    <div class="kpi-wrap">
        <div class="kpi">
            <div class="label">Total envios</div>
            <div class="value">{{ number_format($totalEnvios) }}</div>
        </div>
        <div class="kpi">
            <div class="label">Total malencaminados</div>
            <div class="value">{{ number_format($totalMalencaminados) }}</div>
        </div>
        <div class="kpi">
            <div class="label">% error general</div>
            <div class="value">{{ number_format($porcentajeErrorGeneral, 2) }}%</div>
        </div>
    </div>

    <div class="section-title">Resumen por departamento (origen)</div>
    <table>
        <thead>
            <tr>
                <th>Departamento</th>
                <th>Total envios</th>
                <th>Malencaminados</th>
                <th>% error</th>
                <th>Total registros</th>
                <th>Total malencaminamientos</th>
                <th>EMS</th>
                <th>Contratos</th>
                <th>Certi.</th>
                <th>Ordi.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumen as $row)
                <tr>
                    <td>{{ $row->departamento }}</td>
                    <td>{{ number_format((int) $row->total_envios) }}</td>
                    <td>{{ number_format((int) $row->total_registros) }}</td>
                    <td>{{ number_format((float) $row->porcentaje_error, 2) }}%</td>
                    <td>{{ number_format((int) $row->total_registros) }}</td>
                    <td>{{ number_format((int) $row->total_malencaminamientos) }}</td>
                    <td>{{ number_format((int) $row->ems) }}</td>
                    <td>{{ number_format((int) $row->contratos) }}</td>
                    <td>{{ number_format((int) $row->certificados) }}</td>
                    <td>{{ number_format((int) $row->ordinarios) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="10">Sin datos para este filtro.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title">Detalle de casos y personal</div>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Codigo</th>
                <th>Tipo</th>
                <th>Origen</th>
                <th>Anterior</th>
                <th>Nuevo</th>
                <th>Creo guia</th>
                <th>Reporto / mando</th>
            </tr>
        </thead>
        <tbody>
            @forelse($detalle as $row)
                <tr>
                    <td>{{ \Carbon\Carbon::parse($row->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $row->codigo }}</td>
                    <td>{{ $row->tipo }}</td>
                    <td>{{ $row->departamento_origen }}</td>
                    <td>{{ $row->destino_anterior }}</td>
                    <td>{{ $row->destino_nuevo }}</td>
                    <td>{{ $row->usuario_creador_guia }}</td>
                    <td>{{ $row->usuario_reporto_malencaminado }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8">Sin detalle para este filtro.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="small" style="margin-top:8px;">
        Documento generado automaticamente por Bolipost.
    </div>
</body>
</html>
