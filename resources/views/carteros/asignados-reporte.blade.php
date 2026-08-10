<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte historico de paquetes por cartero</title>
    <style>
        @page { size: A4 landscape; margin: 10mm; }
        body { font-family: DejaVu Sans, sans-serif; color: #172033; font-size: 10px; }
        h1 { margin: 0 0 12px; text-align: center; font-size: 18px; color: #20539a; }
        .meta, .summary, .detail { width: 100%; border-collapse: collapse; }
        .meta td { padding: 3px 5px; }
        .summary { margin: 12px 0; }
        .summary th, .summary td, .detail th, .detail td {
            border: 1px solid #94a3b8;
            padding: 5px;
            vertical-align: top;
        }
        .summary th, .detail th { background: #20539a; color: #fff; text-align: center; }
        .summary td { text-align: center; font-weight: bold; }
        .center { text-align: center; }
        .event { font-size: 8.5px; line-height: 1.25; }
        .muted { color: #64748b; }
        .footer { margin-top: 12px; font-size: 8px; text-align: right; color: #64748b; }
    </style>
</head>
<body>
    @php
        $types = ['EMS', 'CERTI', 'ORDI', 'CONTRATO', 'SOLICITUD'];
    @endphp

    <h1>Reporte historico de paquetes por cartero</h1>

    <table class="meta">
        <tr>
            <td><strong>Cartero:</strong> {{ $cartero->name }}</td>
            <td><strong>Regional:</strong> {{ $cartero->ciudad ?: 'SIN REGIONAL' }}</td>
        </tr>
        <tr>
            <td><strong>Periodo:</strong> {{ $fechaDesde->format('d/m/Y') }} al {{ $fechaHasta->format('d/m/Y') }}</td>
            <td><strong>Generado por:</strong> {{ $actor->name ?? 'SIN USUARIO' }}</td>
        </tr>
    </table>

    <table class="summary">
        <thead>
            <tr>
                @foreach($types as $type)
                    <th>{{ $type }}</th>
                @endforeach
                <th>TOTAL</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach($types as $type)
                    <td>{{ (int) ($summaryByType[$type] ?? 0) }}</td>
                @endforeach
                <td>{{ $rows->count() }}</td>
            </tr>
        </tbody>
    </table>

    <table class="detail">
        <thead>
            <tr>
                <th style="width:4%">Nro.</th>
                <th style="width:10%">Tipo</th>
                <th style="width:21%">Codigo</th>
                <th style="width:14%">Fecha de salida</th>
                <th style="width:51%">Evento que registra la asignacion</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $index => $row)
                <tr>
                    <td class="center">{{ $index + 1 }}</td>
                    <td class="center">{{ $row['tipo_paquete'] }}</td>
                    <td>{{ $row['codigo'] }}</td>
                    <td class="center">{{ $row['fecha_evento']->format('d/m/Y H:i') }}</td>
                    <td class="event">{{ $row['evento'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center muted">No se encontraron paquetes asignados a este cartero en el periodo seleccionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Generado el {{ now()->format('d/m/Y H:i') }}</div>
</body>
</html>
