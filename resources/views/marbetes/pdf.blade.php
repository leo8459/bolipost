<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Marbete CN 35 {{ $receptaculo }}</title>
    <style>
        @page { margin: 12mm; }
        * { box-sizing: border-box; }
        body { color: #111; font-family: DejaVu Sans, sans-serif; font-size: 11px; margin: 0; }
        .label { border: 2px solid #111; height: 117mm; overflow: hidden; page-break-inside: avoid; position: relative; width: 100%; }
        .top { border-bottom: 1px solid #111; height: 36mm; left: 0; position: absolute; right: 0; top: 0; }
        .barcode-block { left: 10mm; position: absolute; right: 39mm; text-align: center; top: 4mm; }
        .barcode-block img { height: 18mm; max-width: 100%; width: 138mm; }
        .barcode-code { font-family: DejaVu Sans Mono, monospace; font-size: 12px; font-weight: bold; letter-spacing: .7px; margin-top: 1mm; }
        .form-code { font-size: 23px; font-weight: bold; position: absolute; right: 7mm; top: 5mm; }
        .final-mark { font-size: 27px; font-weight: bold; position: absolute; right: 12mm; top: 24mm; }
        .main { bottom: 0; left: 0; position: absolute; right: 0; top: 36mm; }
        .left { border-right: 1px solid #111; bottom: 0; left: 0; padding: 5mm 6mm; position: absolute; top: 0; width: 48%; }
        .right { bottom: 0; left: 48%; padding: 5mm 6mm; position: absolute; right: 0; top: 0; }
        .line { margin-bottom: 2.7mm; }
        .caption { display: inline-block; font-size: 9px; text-transform: uppercase; width: 24mm; }
        .value { font-family: DejaVu Sans Mono, monospace; font-size: 14px; font-weight: bold; }
        .destination { font-size: 19px; margin: 2mm 0 4mm 24mm; }
        .mail-type { font-size: 16px; font-weight: bold; letter-spacing: .5px; margin-bottom: 7mm; text-align: center; }
        .transport-table { border-collapse: collapse; margin-top: 5mm; width: 100%; }
        .transport-table td { border-bottom: 1px solid #777; padding: 2.4mm 1mm; }
        .transport-table td:first-child { font-size: 9px; text-transform: uppercase; width: 23mm; }
        .destination-code { font-size: 20px; font-weight: bold; line-height: 1.1; margin-top: 4mm; padding-right: 6mm; text-align: right; }
        .upu-note { bottom: 2.5mm; color: #555; font-size: 7px; left: 6mm; position: absolute; }
    </style>
</head>
<body>
    <div class="label">
        <div class="top">
            <div class="barcode-block">
                <img src="data:image/png;base64,{{ $barcodePng }}" alt="Codigo de barras {{ $receptaculo }}">
                <div class="barcode-code">{{ $receptaculo }}</div>
            </div>
            <div class="form-code">CN 35</div>
            @if ((int) $ultimo_receptaculo === 1)<div class="final-mark">F</div>@endif
        </div>
        <div class="main">
            <div class="left">
                <div class="line"><span class="caption">De:</span><span class="value">{{ $origen_impc }}</span></div>
                <div class="line"><span class="caption">Para:</span><span class="value">{{ $destino_impc }}</span></div>
                <div class="mail-type">{{ $tipo_correo }}</div>
                <div class="line"><span class="caption">Despacho:</span><span class="value">{{ $numero_despacho_formateado }}</span></div>
                <div class="line"><span class="caption">Fecha:</span><span class="value">{{ \Illuminate\Support\Carbon::parse($fecha)->format('d/m/Y') }}</span></div>
                <div class="line"><span class="caption">Recept.:</span><span class="value">{{ $numero_receptaculo_formateado }}/{{ $subclase }}</span></div>
                <div class="line"><span class="caption">Sacas:</span><span class="value">{{ $cantidad_envios_formateada }}</span></div>
                <div class="line"><span class="caption">Peso/kg:</span><span class="value">{{ \App\Support\BolivianNumber::format($peso, 1, ',', '.') }}</span></div>
            </div>
            <div class="right">
                <div class="line"><span class="caption">Para:</span></div>
                <div class="destination value">{{ $ciudad_destino }}<br>{{ $pais_destino }}</div>
                <table class="transport-table">
                    <tr><td>Vuelo:</td><td class="value">{{ $vuelo ?: '-' }}</td></tr>
                    <tr><td>Tren:</td><td class="value">{{ $tren ?: '-' }}</td></tr>
                    <tr><td>Pais destino:</td><td class="value">{{ $descarga ?: '-' }}</td></tr>
                </table>
                <div class="destination-code">{{ $descarga ?: '-' }}</div>
            </div>
            <div class="upu-note">Identificador de receptaculo UPU S9 - Categoria A</div>
        </div>
    </div>
</body>
</html>
