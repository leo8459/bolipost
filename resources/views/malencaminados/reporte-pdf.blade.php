<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Malencaminados</title>
    <style>
        @page { margin: 16px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; }
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
            <div class="label">Total departamentos</div>
            <div class="value">{{ $resumen->count() }}</div>
        </div>
        <div class="kpi">
            <div class="label">Total registros</div>
            <div class="value">{{ (int) $resumen->sum('total_registros') }}</div>
        </div>
        <div class="kpi">
            <div class="label">Total malencaminamientos</div>
            <div class="value">{{ (int) $resumen->sum('total_malencaminamientos') }}</div>
        </div>
    </div>

    <div class="section-title">Resumen por departamento (origen)</div>
    <table>
        <thead>
            <tr>
                <th>Departamento</th>
                <th>Total registros</th>
                <th>Total malencaminamientos</th>
            </tr>
        </thead>
        <tbody>
            @forelse($resumen as $row)
                <tr>
                    <td>{{ $row->departamento }}</td>
                    <td>{{ (int) $row->total_registros }}</td>
                    <td>{{ (int) $row->total_malencaminamientos }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Sin datos para este filtro.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="small" style="margin-top:8px;">
        Documento generado automaticamente por Bolipost.
    </div>
</body>
</html>
