<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de paquetes por empresa</title>
    <style>
        @page { size: A4 landscape; margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #182230; font-family: DejaVu Sans, sans-serif; font-size: 8px; }
        .header { border-bottom: 3px solid #20539A; margin-bottom: 10px; padding-bottom: 9px; }
        .header-table, .summary, .report { border-collapse: collapse; width: 100%; }
        .header-table td { vertical-align: middle; }
        .logo { height: 38px; margin-bottom: 4px; }
        .title { color: #20539A; font-size: 18px; font-weight: 800; }
        .subtitle { color: #667085; font-size: 9px; margin-top: 3px; }
        .meta { line-height: 1.55; text-align: right; }
        .summary { border-spacing: 7px 0; border-collapse: separate; margin: 0 -7px 10px; }
        .summary td { background: #f1f6ff; border: 1px solid #d5e3f7; border-radius: 7px; padding: 7px 9px; width: 25%; }
        .summary-label { color: #667085; font-size: 7px; font-weight: 700; text-transform: uppercase; }
        .summary-value { color: #163f78; font-size: 13px; font-weight: 800; margin-top: 2px; }
        .filters { color: #475467; margin-bottom: 9px; }
        .filter { background: #fff8df; border: 1px solid #f2d675; border-radius: 10px; display: inline-block; margin-right: 5px; padding: 4px 8px; }
        .report { table-layout: fixed; }
        .report thead { display: table-header-group; }
        .report tr { page-break-inside: avoid; }
        .report th { background: #20539A; border: 1px solid #174578; color: #fff; font-size: 7px; padding: 6px 5px; text-align: left; text-transform: uppercase; }
        .report td { border: 1px solid #dbe4f0; line-height: 1.3; padding: 5px; vertical-align: top; word-wrap: break-word; }
        .report tbody tr:nth-child(even) td { background: #f8fbff; }
        .code { color: #174578; font-size: 8px; font-weight: 800; }
        .state { font-weight: 700; }
        .muted { color: #667085; }
        .route { font-weight: 700; }
        .weight { text-align: right; white-space: nowrap; }
        .evidence { text-align: center; }
        .evidence img { border: 1px solid #cbd8e8; border-radius: 5px; height: 58px; object-fit: cover; width: 78px; }
        .download-note { color: #20539A; font-size: 6px; font-weight: 700; margin-top: 2px; }
        .empty { color: #667085; padding: 20px !important; text-align: center; }
        .footer { bottom: -9mm; color: #98a2b3; font-size: 7px; left: 0; position: fixed; right: 0; text-align: center; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/AGBClogo1.png');
        $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
        $estadoLabel = match ($estadoFiltro) {
            'entregados' => 'Entregados',
            'todos' => 'Todos',
            default => 'Pendientes',
        };
    @endphp

    <div class="footer">
        Reporte generado por TrackingBO - {{ $generatedAt->format('d/m/Y H:i') }}
    </div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td>
                    @if($logoB64)
                        <img src="data:image/png;base64,{{ $logoB64 }}" class="logo" alt="Correos de Bolivia">
                    @endif
                    <div class="title">Reporte de paquetes de contrato</div>
                    <div class="subtitle">Detalle por codigo cliente con evidencias fotograficas de entrega</div>
                </td>
                <td class="meta">
                    <div><strong>Empresa:</strong> {{ $empresa?->nombre ?: 'Sin empresa' }}</div>
                    <div><strong>Codigo cliente:</strong> {{ $empresa?->codigo_cliente ?: '-' }}</div>
                    <div><strong>Usuario:</strong> {{ $usuarioNombre ?: '-' }}</div>
                    <div><strong>Generado:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td><div class="summary-label">Registros</div><div class="summary-value">{{ number_format($contratos->count()) }}</div></td>
            <td><div class="summary-label">Peso total</div><div class="summary-value">{{ number_format($totalPeso, 3) }} kg</div></td>
            <td><div class="summary-label">Con imagen</div><div class="summary-value">{{ number_format($totalImagenes) }}</div></td>
            <td><div class="summary-label">Filtro</div><div class="summary-value">{{ $estadoLabel }}</div></td>
        </tr>
    </table>

    <div class="filters">
        <span class="filter"><strong>Estado:</strong> {{ $estadoLabel }}</span>
        @if($search !== '')
            <span class="filter"><strong>Busqueda:</strong> {{ $search }}</span>
        @endif
        <span class="filter"><strong>Alcance:</strong> empresas con codigo cliente {{ $empresa?->codigo_cliente ?: '-' }}</span>
        <span class="filter"><strong>Descarga de imagenes:</strong> enlaces validos por {{ $publicImageLinkDays }} dias</span>
    </div>

    <table class="report">
        <thead>
            <tr>
                <th style="width: 11%;">Codigo / fecha</th>
                <th style="width: 8%;">Estado</th>
                <th style="width: 13%;">Ruta</th>
                <th style="width: 18%;">Remitente</th>
                <th style="width: 18%;">Destinatario</th>
                <th style="width: 16%;">Contenido</th>
                <th style="width: 6%;">Peso</th>
                <th style="width: 10%; text-align:center;">Imagen</th>
            </tr>
        </thead>
        <tbody>
            @forelse($contratos as $contrato)
                <tr>
                    <td>
                        <div class="code">{{ $contrato->codigo }}</div>
                        <div class="muted">{{ optional($contrato->created_at)->format('d/m/Y H:i') ?: '-' }}</div>
                    </td>
                    <td class="state">{{ optional($contrato->estadoRegistro)->nombre_estado ?: '-' }}</td>
                    <td>
                        <div class="route">{{ $contrato->origen ?: '-' }} - {{ $contrato->destino ?: '-' }}</div>
                        @if($contrato->provincia)<div class="muted">{{ $contrato->provincia }}</div>@endif
                    </td>
                    <td>
                        <strong>{{ $contrato->nombre_r ?: '-' }}</strong><br>
                        <span class="muted">{{ $contrato->telefono_r ?: '-' }}</span><br>
                        <span class="muted">{{ $contrato->direccion_r ?: '-' }}</span>
                    </td>
                    <td>
                        <strong>{{ $contrato->nombre_d ?: '-' }}</strong><br>
                        <span class="muted">{{ $contrato->telefono_d ?: '-' }}</span><br>
                        <span class="muted">{{ $contrato->direccion_d ?: '-' }}</span>
                    </td>
                    <td>{{ $contrato->contenido ?: '-' }}</td>
                    <td class="weight">{{ number_format((float) ($contrato->peso ?? 0), 3) }} kg</td>
                    <td class="evidence">
                        @if($contrato->imagen_pdf)
                            <a href="{{ $contrato->imagen_descarga_url }}">
                                <img src="{{ $contrato->imagen_pdf }}" alt="Imagen de {{ $contrato->codigo }}">
                            </a>
                            <div class="download-note">Clic para descargar</div>
                        @else
                            <span class="muted">Sin imagen</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No existen registros para los filtros seleccionados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
