<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificacion de guia EMS</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 18px; display: flex; align-items: center; justify-content: center; font-family: Verdana, DejaVu Sans, sans-serif; color: #101010; background: #f2f4f7; }
        .panel { width: 100%; max-width: 560px; overflow: hidden; background: #fff; border: 1px solid #d7dde6; border-radius: 10px; box-shadow: 0 16px 44px rgba(0, 0, 0, .12); }
        .header { display: flex; align-items: center; gap: 14px; padding: 18px 20px; border-bottom: 1px solid #d7dde6; }
        .logo { width: 128px; max-height: 44px; object-fit: contain; }
        .title { margin: 0; font-size: 20px; line-height: 1.15; font-weight: 800; }
        .subtitle { margin: 3px 0 0; color: #566170; font-size: 13px; }
        .body { padding: 20px; }
        .code { display: inline-block; padding: 8px 12px; background: #fff; border: 1px solid #101010; border-radius: 999px; font-size: 15px; font-weight: 800; letter-spacing: .3px; }
        .verified { display: inline-block; margin-left: 7px; padding: 7px 10px; color: #146c43; background: #d1e7dd; border-radius: 999px; font-size: 12px; font-weight: 800; }
        .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 16px; }
        .item { min-height: 64px; padding: 10px 12px; border: 1px solid #d7dde6; border-radius: 8px; }
        .label { display: block; margin-bottom: 5px; color: #566170; font-size: 11px; font-weight: 800; text-transform: uppercase; }
        .value { font-size: 14px; font-weight: 700; word-break: break-word; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 0 20px 20px; }
        .btn { display: block; padding: 13px 14px; color: #101010; background: #fff; border: 1px solid #101010; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 800; }
        .btn-primary { color: #fff; background: #101010; }
        @media (max-width: 520px) { .header { align-items: flex-start; flex-direction: column; } .grid, .actions { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/AGBClogo1.png');
        $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
    @endphp
    <main class="panel">
        <div class="header">
            @if($logoB64)
                <img class="logo" src="data:image/png;base64,{{ $logoB64 }}" alt="Correos de Bolivia">
            @endif
            <div><h1 class="title">Guia EMS verificada</h1><p class="subtitle">QR cifrado de verificacion</p></div>
        </div>
        <div class="body">
            <span class="code">{{ $paquete->codigo }}</span><span class="verified">Documento valido</span>
            <div class="grid">
                <div class="item"><span class="label">Remitente</span><span class="value">{{ $paquete->nombre_remitente ?: '-' }}</span></div>
                <div class="item"><span class="label">Destinatario</span><span class="value">{{ $paquete->nombre_destinatario ?: '-' }}</span></div>
                <div class="item"><span class="label">Origen</span><span class="value">{{ $paquete->origen ?: '-' }}</span></div>
                <div class="item"><span class="label">Destino</span><span class="value">{{ $paquete->ciudad ?: '-' }}</span></div>
                <div class="item"><span class="label">Cantidad</span><span class="value">{{ $paquete->cantidad ?: '-' }}</span></div>
                <div class="item"><span class="label">Peso</span><span class="value">{{ $paquete->peso !== null ? number_format((float) $paquete->peso, 3) . ' kg' : '-' }}</span></div>
                <div class="item"><span class="label">Fecha</span><span class="value">{{ optional($paquete->created_at)->format('d/m/Y H:i') ?: '-' }}</span></div>
                <div class="item"><span class="label">Servicio</span><span class="value">{{ optional(optional($paquete->tarifario)->servicio)->nombre_servicio ?: ($paquete->tipo_correspondencia ?: '-') }}</span></div>
            </div>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="{{ $reimprimirUrl }}" target="_blank" rel="noopener">Reimprimir</a>
            <a class="btn" href="{{ $rastrearUrl }}" target="_blank" rel="noopener">Rastrear</a>
        </div>
    </main>
</body>
</html>
