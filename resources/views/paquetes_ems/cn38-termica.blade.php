<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $documentType ?? 'CN-38' }} {{ $despacho }}</title>
    <style>
        @page { margin: 3mm 4mm; }
        * { box-sizing: border-box; }
        html, body {
            margin: 0;
            padding: 0;
            font-family: Verdana, "DejaVu Sans", sans-serif;
            color: #000;
            background: #fff;
        }
        body {
            width: 72mm;
            margin: 0 auto;
            font-size: 9px;
            line-height: 1.3;
        }
        .ticket { width: 72mm; }
        .center { text-align: center; }
        .logo {
            max-width: 55mm;
            max-height: 15mm;
            margin: 0 auto 1mm;
        }
        .heading {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .04em;
        }
        .document-type {
            margin-top: 1mm;
            font-size: 15px;
            font-weight: 700;
        }
        .dispatch {
            margin-top: 1mm;
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 2mm 0;
        }
        .line { margin: .8mm 0; }
        .label { font-weight: 700; }
        .bag {
            padding: 1.5mm 0;
            border-bottom: 1px dashed #000;
            page-break-inside: avoid;
        }
        .bag-title {
            font-size: 10px;
            font-weight: 700;
            overflow-wrap: anywhere;
        }
        .weight { font-size: 10px; font-weight: 700; }
        .totals {
            margin-top: 2mm;
            padding: 2mm 1mm;
            border: 1px solid #000;
            font-size: 10px;
        }
        .stamp {
            display: inline-block;
            margin-top: 4mm;
            padding: 1.5mm 2mm;
            border: 1px solid #000;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .04em;
        }
        .signature {
            margin-top: 8mm;
            padding-top: 1mm;
            border-top: 1px solid #000;
            text-align: center;
            font-size: 8px;
        }
        .footer { margin-top: 3mm; text-align: center; font-size: 8px; }
    </style>
</head>
<body>
@php
    $fecha = $generatedAt instanceof \Carbon\CarbonInterface
        ? $generatedAt
        : \Illuminate\Support\Carbon::parse($generatedAt);
    $documentType = $documentType ?? 'CN-38';
    $origenCode = mb_strtoupper(mb_substr((string) $loggedInUserCity, 0, 3));
    $destinoCode = mb_strtoupper(mb_substr((string) $destinationCity, 0, 3));
    $logoPath = public_path('images/LOGO 19-2-26.png');
    $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
@endphp
<div class="ticket">
    <div class="center">
        @if($logoB64)
            <img class="logo" src="data:image/png;base64,{{ $logoB64 }}" alt="Correos de Bolivia">
        @endif
        <div class="heading">FACTURA DE ENTREGA</div>
        <div>{{ $fecha->format('d/m/Y') }}</div>
        <div class="document-type">{{ $documentType }}</div>
        <div class="dispatch">{{ strtoupper((string) $despacho) }}</div>
    </div>

    <div class="divider"></div>
    <div class="line"><span class="label">Administración expedidora:</span> BO - BOLIVIA</div>
    <div class="line"><span class="label">Oficina de cambio:</span> {{ $origenCode }} - {{ strtoupper((string) $loggedInUserCity) }}</div>
    <div class="line"><span class="label">Oficina de destino:</span> {{ $destinoCode }} - {{ strtoupper((string) $destinationCity) }}</div>
    <div class="line"><span class="label">Itinerario:</span> {{ strtoupper((string) $selectedTransport) }}</div>
    <div class="line"><span class="label">Apto. tránsito:</span> {{ strtoupper((string) $selectedTransport) }}</div>
    <div class="line"><span class="label">Salida:</span> {{ $fecha->format('d/m/Y') }} &nbsp; <span class="label">Vuelo:</span> {{ $transportNumber }}</div>

    <div class="divider"></div>
    <div class="label center">SACAS / DESPACHOS</div>
    @foreach ($rows as $row)
        @php
            $despachoEtiqueta = strtoupper((string) data_get($row, 'despacho_etiqueta', data_get($row, 'despacho', '-')));
            $origen = strtoupper((string) data_get($row, 'origen', '-'));
            $destino = strtoupper((string) data_get($row, 'destino', '-'));
            $peso = \App\Support\BolivianNumber::format((float) data_get($row, 'peso_total', 0), 1);
        @endphp
        <div class="bag">
            <div class="bag-title">{{ $despachoEtiqueta }}</div>
            <div class="line"><span class="label">Origen:</span> {{ $origen }}</div>
            <div class="line"><span class="label">Destino:</span> {{ $destino }}</div>
            <div class="line"><span class="label">Tipo:</span> EMS <span class="label">Peso:</span> <span class="weight">{{ $peso }} kg</span></div>
        </div>
    @endforeach

    <div class="totals">
        <div><span class="label">Total de sacas:</span> {{ $rows->count() }}</div>
        <div><span class="label">Peso total:</span> {{ \App\Support\BolivianNumber::format((float) $totalPeso, 1) }} kg</div>
    </div>

    <div class="center"><span class="stamp">EMS RECEPCIÓN</span></div>
    <div class="signature">{{ strtoupper((string) $loggedUserName) }}<br>OFICINA EXPEDIDORA</div>
    <div class="signature">OFICINA RECEPTORA</div>
    <div class="footer">{{ $documentType }} · {{ $fecha->format('d/m/Y H:i') }}</div>
</div>
</body>
</html>
