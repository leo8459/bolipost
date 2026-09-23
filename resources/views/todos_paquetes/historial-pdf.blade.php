<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de historial de paquetes</title>
    <style>
        @page { margin: 24mm 13mm 17mm 13mm; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            line-height: 1.35;
        }
        .header {
            margin-bottom: 12px;
            border-bottom: 3px solid #f2b705;
            padding-bottom: 8px;
        }
        .header-table, .summary-table, .package-meta, .events { width: 100%; border-collapse: collapse; }
        .logo { width: 128px; max-height: 48px; object-fit: contain; }
        .institution { color: #17457b; text-align: right; text-transform: uppercase; }
        .institution strong { display: block; font-size: 11px; }
        h1 {
            margin: 10px 0 3px;
            color: #17457b;
            font-size: 20px;
            line-height: 1.1;
            text-transform: uppercase;
        }
        .subtitle { color: #5f6f82; font-size: 9px; }
        .summary-table { margin: 10px 0 12px; }
        .summary-table td {
            width: 25%;
            border: 1px solid #cdd8e5;
            border-top: 3px solid #17457b;
            padding: 7px 8px;
            vertical-align: top;
        }
        .label {
            display: block;
            margin-bottom: 2px;
            color: #60748b;
            font-size: 7px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .value { color: #173f70; font-size: 11px; font-weight: bold; }
        .notice {
            margin: 0 0 12px;
            border-left: 4px solid #f2b705;
            background: #fff9df;
            padding: 7px 9px;
            color: #5f4a08;
        }
        .missing { border-left-color: #c53030; background: #fff1f1; color: #7f1d1d; }
        .package { margin: 0 0 14px; page-break-inside: avoid; }
        .package-title {
            border-left: 5px solid #f2b705;
            background: #17457b;
            color: #fff;
            padding: 7px 9px;
            page-break-after: avoid;
        }
        .package-title strong { font-size: 13px; }
        .package-title span { float: right; font-size: 8px; text-transform: uppercase; }
        .package-meta { page-break-after: avoid; }
        .package-meta td {
            width: 25%;
            border: 1px solid #d8e0e9;
            background: #f7f9fc;
            padding: 6px 7px;
            vertical-align: top;
        }
        .current {
            border: 1px solid #b8c9dc;
            border-top: 0;
            background: #edf4fb;
            padding: 7px 8px;
            page-break-after: avoid;
        }
        .current strong { color: #17457b; }
        .events { margin-top: 5px; }
        .events thead { display: table-header-group; }
        .events tr { page-break-inside: avoid; }
        .events th {
            border: 1px solid #17457b;
            background: #17457b;
            color: #fff;
            padding: 5px;
            font-size: 7px;
            text-align: left;
            text-transform: uppercase;
        }
        .events td {
            border: 1px solid #d6dee8;
            padding: 5px;
            vertical-align: top;
        }
        .events tbody tr:nth-child(even) td { background: #f7f9fc; }
        .number { width: 5%; text-align: center; }
        .date { width: 15%; white-space: nowrap; }
        .elapsed { width: 14%; }
        .event { width: 29%; }
        .user { width: 24%; }
        .regional { width: 13%; }
        .event-name { color: #173f70; font-weight: bold; }
        .muted { color: #6b7b8f; font-size: 7px; }
        .next-time {
            display: block;
            margin-top: 5px;
            border-left: 2px solid #f2b705;
            background: #fff9df;
            padding: 3px 4px;
            color: #5f4a08;
            font-size: 6.5px;
            line-height: 1.25;
            white-space: normal;
        }
        .empty {
            border: 1px solid #ecd68c;
            background: #fffaf0;
            padding: 9px;
            color: #765c12;
            text-align: center;
        }
        .footer {
            position: fixed;
            right: 0;
            bottom: -11mm;
            left: 0;
            border-top: 1px solid #cdd8e5;
            padding-top: 4px;
            color: #718096;
            font-size: 7px;
            text-align: center;
        }
    </style>
</head>
<body>
@php
    $logoPath = public_path('images/AGBClogo2.png');
    $logoData = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    $statusCount = $packages->groupBy(fn ($package) => trim((string) $package->estado_nombre) ?: 'SIN ESTADO')
        ->map->count()
        ->sortDesc();
@endphp

<div class="footer">
    Documento generado por el sistema de Correos de Bolivia el {{ $generatedAt->format('d/m/Y H:i:s') }}.
</div>

<header class="header">
    <table class="header-table">
        <tr>
            <td>
                @if($logoData)
                    <img class="logo" src="data:image/png;base64,{{ $logoData }}" alt="Correos de Bolivia">
                @endif
            </td>
            <td class="institution">
                <strong>Correos de Bolivia</strong>
                Informe de trazabilidad operativa
            </td>
        </tr>
    </table>
    <h1>Reporte de historial de paquetes</h1>
    <div class="subtitle">Detalle completo de movimientos registrados, usuarios responsables, regionales y fechas.</div>
</header>

<table class="summary-table">
    <tr>
        <td><span class="label">Fecha de emisión</span><span class="value">{{ $generatedAt->format('d/m/Y H:i') }}</span></td>
        <td><span class="label">Generado por</span><span class="value">{{ $generatedBy?->name ?: 'Usuario del sistema' }}</span></td>
        <td><span class="label">Guías encontradas</span><span class="value">{{ $packages->count() }} / {{ $requestedCodes->count() }}</span></td>
        <td><span class="label">Movimientos registrados</span><span class="value">{{ $totalEvents }}</span></td>
    </tr>
</table>

@if($statusCount->isNotEmpty())
    <div class="notice">
        <strong>Resumen por estado actual:</strong>
        {{ $statusCount->map(fn ($count, $status) => $status.': '.$count)->implode(' | ') }}
    </div>
@endif

@if($notFoundCodes->isNotEmpty())
    <div class="notice missing">
        <strong>Guías no encontradas ({{ $notFoundCodes->count() }}):</strong>
        {{ $notFoundCodes->implode(', ') }}.
        Verifica que los códigos estén completos y correctamente escritos.
    </div>
@endif

@forelse($packages as $index => $package)
    @php
        $events = $package->eventos;
        $lastEvent = $events->last();
        $lastActor = trim((string) ($lastEvent->usuario_nombre ?? ''));
        if ($lastActor === '') {
            $lastActor = trim((string) ($lastEvent->cliente_nombre ?? '')) ?: 'Sistema / usuario no identificado';
        }
    @endphp
    <section class="package">
        <div class="package-title">
            <strong>{{ $index + 1 }}. {{ $package->codigo ?: 'SIN CÓDIGO' }}</strong>
            <span>{{ $package->tipo }} · {{ $events->count() }} movimiento(s)</span>
        </div>
        <table class="package-meta">
            <tr>
                <td><span class="label">Origen</span><strong>{{ $package->origen ?: 'NO REGISTRADO' }}</strong></td>
                <td><span class="label">Destino</span><strong>{{ $package->destino ?: 'NO REGISTRADO' }}</strong></td>
                <td><span class="label">Estado actual</span><strong>{{ $package->estado_nombre ?: 'SIN ESTADO' }}</strong></td>
                <td><span class="label">Última actualización</span><strong>{{ $package->updated_at ? \Illuminate\Support\Carbon::parse($package->updated_at)->format('d/m/Y H:i') : 'SIN FECHA' }}</strong></td>
            </tr>
            <tr>
                <td><span class="label">Destinatario</span>{{ $package->destinatario ?: 'NO REGISTRADO' }}</td>
                <td><span class="label">Remitente / empresa</span>{{ $package->remitente ?: ($package->empresa ?: 'NO REGISTRADO') }}</td>
                <td><span class="label">Peso</span>{{ $package->peso !== '' ? \App\Support\BolivianNumber::format($package->peso, 3).' kg' : 'NO REGISTRADO' }}</td>
                <td><span class="label">Monto / observación</span>{{ $package->precio !== '' ? 'Bs '.\App\Support\BolivianNumber::format($package->precio, 2) : ($package->justificacion ?: 'NO REGISTRADO') }}</td>
            </tr>
        </table>

        @if($lastEvent)
            <div class="current">
                <span class="label">Qué pasó por última vez</span>
                <strong>{{ $lastEvent->nombre_evento }}</strong>
                — {{ $lastEvent->created_at ? \Illuminate\Support\Carbon::parse($lastEvent->created_at)->format('d/m/Y H:i:s') : 'sin fecha' }}
                — {{ $lastActor }}
                <br><span class="muted"><strong>Duración total registrada:</strong> {{ $package->duracion_historial ?? 'Sin historial' }}</span>
            </div>
        @endif

        @if($events->isNotEmpty())
            <table class="events">
                <thead>
                    <tr>
                        <th class="number">N°</th>
                        <th class="date">Fecha y hora</th>
                        <th class="elapsed">Tiempo desde el evento anterior</th>
                        <th class="event">Movimiento / evento</th>
                        <th class="user">Usuario responsable</th>
                        <th class="regional">Regional</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($events as $eventIndex => $event)
                        @php
                            $actorName = trim((string) ($event->usuario_nombre ?? ''));
                            $actorAlias = trim((string) ($event->usuario_alias ?? ''));
                            $actorEmail = trim((string) ($event->usuario_email ?? ''));
                            $clientName = trim((string) ($event->cliente_nombre ?? ''));
                        @endphp
                        <tr>
                            <td class="number">{{ $eventIndex + 1 }}</td>
                            <td class="date">
                                {{ $event->created_at ? \Illuminate\Support\Carbon::parse($event->created_at)->format('d/m/Y') : 'SIN FECHA' }}<br>
                                <span class="muted">{{ $event->created_at ? \Illuminate\Support\Carbon::parse($event->created_at)->format('H:i:s') : '' }}</span>
                                <span class="next-time">
                                    @if(($event->tiempo_hasta_siguiente ?? '') === 'Último evento registrado')
                                        Último evento registrado
                                    @else
                                        Siguiente evento en:<br><strong>{{ $event->tiempo_hasta_siguiente }}</strong>
                                    @endif
                                </span>
                            </td>
                            <td class="elapsed"><strong>{{ $event->tiempo_desde_anterior ?? 'Evento inicial' }}</strong></td>
                            <td class="event">
                                <span class="event-name">{{ $event->nombre_evento }}</span>
                                @if(trim((string) ($event->detalle_evento ?? '')) !== '' && trim((string) $event->detalle_evento) !== trim((string) $event->nombre_evento))
                                    <br><span class="muted">Detalle: {{ $event->detalle_evento }}</span>
                                @endif
                            </td>
                            <td class="user">
                                <strong>{{ $actorName ?: ($clientName ?: 'Sistema / no identificado') }}</strong>
                                @if($actorAlias !== '')<br><span class="muted">Usuario: {{ $actorAlias }}</span>@endif
                                @if($actorEmail !== '')<br><span class="muted">{{ $actorEmail }}</span>@endif
                            </td>
                            <td class="regional">{{ mb_strtoupper(trim((string) ($event->usuario_regional ?? ''))) ?: 'NO REGISTRADA' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @else
            <div class="empty">
                Esta guía no tiene movimientos en las tablas de historial. Su estado actual es
                <strong>{{ $package->estado_nombre ?: 'SIN ESTADO' }}</strong>
                y su última actualización fue
                <strong>{{ $package->updated_at ? \Illuminate\Support\Carbon::parse($package->updated_at)->format('d/m/Y H:i:s') : 'SIN FECHA' }}</strong>.
            </div>
        @endif
    </section>
@empty
    <div class="empty">No se encontró ninguna de las guías solicitadas.</div>
@endforelse
</body>
</html>
