<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de contratos entregados</title>
    <style>
        @page { size: A4 landscape; margin: 14mm 12mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 10px; }
        .header { border-bottom: 2px solid #20539A; padding-bottom: 10px; margin-bottom: 14px; }
        .brand { font-size: 20px; font-weight: 800; color: #20539A; letter-spacing: .4px; }
        .subtitle { margin-top: 4px; color: #64748b; font-size: 10px; }
        .meta { margin-top: 8px; width: 100%; border-collapse: collapse; }
        .meta td { vertical-align: top; }
        .meta-right { text-align: right; }
        .chips { margin: 10px 0 14px; }
        .chip { display: inline-block; background: #eef4ff; color: #20539A; border: 1px solid #c8d9f8; border-radius: 999px; padding: 5px 10px; margin-right: 6px; font-weight: 700; }
        .summary { width: 100%; border-collapse: separate; border-spacing: 10px 0; margin: 0 -10px 14px; }
        .summary td { width: 25%; background: linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%); border: 1px solid #d8e5fb; border-radius: 10px; padding: 10px 12px; }
        .k { color: #64748b; font-size: 9px; text-transform: uppercase; margin-bottom: 4px; }
        .v { color: #0f172a; font-size: 18px; font-weight: 800; }
        .section-title { margin: 16px 0 8px; font-size: 12px; font-weight: 800; color: #20539A; }
        table.report { width: 100%; border-collapse: collapse; table-layout: fixed; }
        table.report th, table.report td { border: 1px solid #dbe4f2; padding: 7px 8px; vertical-align: top; word-wrap: break-word; }
        table.report th { background: #20539A; color: #fff; font-weight: 800; font-size: 9px; text-transform: uppercase; }
        table.report tbody tr:nth-child(even) td { background: #f8fbff; }
        .num { text-align: right; }
        .muted { color: #64748b; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/AGBClogo1.png');
        $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    @endphp
    <div class="header">
        <table class="meta">
            <tr>
                <td>
                    @if($logoB64)
                        <div style="margin-bottom:8px;">
                            <img src="data:image/png;base64,{{ $logoB64 }}" alt="Correos de Bolivia" style="height:44px;">
                        </div>
                    @endif
                    <div class="brand">Reporte Profesional de Contratos Entregados</div>
                    <div class="subtitle">Correos de Bolivia | Seguimiento de entregas por rango de fechas</div>
                </td>
                <td class="meta-right">
                    <div><strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</div>
                    <div><strong>Usuario:</strong> {{ $usuarioNombre }}</div>
                    <div><strong>Empresa:</strong> {{ $empresaNombre }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="chips">
        <span class="chip">Desde: {{ $fechaDesde ? \Illuminate\Support\Carbon::parse($fechaDesde)->format('d/m/Y') : 'Inicio' }}</span>
        <span class="chip">Hasta: {{ $fechaHasta ? \Illuminate\Support\Carbon::parse($fechaHasta)->format('d/m/Y') : 'Hoy' }}</span>
        <span class="chip">Registros: {{ number_format($stats['total'] ?? 0) }}</span>
    </div>

    <table class="summary">
        <tr>
            <td><div class="k">Total entregados</div><div class="v">{{ number_format($stats['total'] ?? 0) }}</div></td>
            <td><div class="k">Peso total</div><div class="v">{{ number_format((float) ($stats['peso_total'] ?? 0), 3) }} kg</div></td>
            <td><div class="k">Dias con entregas</div><div class="v">{{ number_format($stats['dias_cubiertos'] ?? 0) }}</div></td>
            <td><div class="k">Promedio diario</div><div class="v">{{ number_format((float) ($stats['promedio_diario'] ?? 0), 2) }}</div></td>
        </tr>
    </table>

    <div class="section-title">Resumen por dia</div>
    <table class="report">
        <thead>
            <tr>
                <th style="width: 35%;">Fecha</th>
                <th style="width: 20%;" class="num">Entregados</th>
                <th style="width: 20%;" class="num">Peso (kg)</th>
                <th>Observacion</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($stats['por_dia'] ?? collect()) as $row)
                <tr>
                    <td>{{ $row['label'] }}</td>
                    <td class="num">{{ number_format((int) $row['total']) }}</td>
                    <td class="num">{{ number_format((float) $row['peso'], 3) }}</td>
                    <td class="muted">Distribucion diaria de contratos entregados.</td>
                </tr>
            @empty
                <tr><td colspan="4" class="muted">Sin resumen diario para mostrar.</td></tr>
            @endforelse
        </tbody>
    </table>

    <div class="section-title page-break">Detalle de contratos entregados</div>
    <table class="report">
        <thead>
            <tr>
                <th style="width: 12%;">Codigo</th>
                <th style="width: 10%;">Fecha de entrega</th>
                <th style="width: 8%;">Origen</th>
                <th style="width: 8%;">Destino</th>
                <th style="width: 16%;">Remitente</th>
                <th style="width: 16%;">Destinatario</th>
                <th style="width: 14%;">Contenido</th>
                <th style="width: 6%;" class="num">Cant.</th>
                <th style="width: 6%;" class="num">Peso</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contratos as $contrato)
                <tr>
                    <td>{{ $contrato->codigo }}</td>
                    <td>{{ optional($contrato->created_at)->format('d/m/Y H:i') ?: '-' }}</td>
                    <td>{{ $contrato->origen ?: '-' }}</td>
                    <td>{{ $contrato->destino ?: '-' }}</td>
                    <td>{{ $contrato->nombre_r ?: '-' }}</td>
                    <td>{{ $contrato->nombre_d ?: '-' }}</td>
                    <td>{{ $contrato->contenido ?: '-' }}</td>
                    <td class="num">{{ $contrato->cantidad ?: '-' }}</td>
                    <td class="num">{{ number_format((float) ($contrato->peso ?? 0), 3) }}</td>
                    <td>{{ optional($contrato->user)->name ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="muted">No hay contratos entregados en el rango indicado.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
