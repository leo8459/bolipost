<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verificacion de guia</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: Verdana, DejaVu Sans, sans-serif;
            color: #101010;
            background: #f2f4f7;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 18px;
        }
        .panel {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border: 1px solid #d7dde6;
            border-radius: 10px;
            box-shadow: 0 16px 44px rgba(0, 0, 0, .12);
            overflow: hidden;
        }
        .header {
            padding: 18px 20px;
            border-bottom: 1px solid #d7dde6;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .logo {
            width: 128px;
            max-height: 44px;
            object-fit: contain;
        }
        .title {
            margin: 0;
            font-size: 20px;
            line-height: 1.15;
            font-weight: 800;
        }
        .subtitle {
            margin: 3px 0 0;
            color: #566170;
            font-size: 13px;
        }
        .body { padding: 20px; }
        .code {
            display: inline-block;
            padding: 8px 12px;
            border: 1px solid #101010;
            border-radius: 999px;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .3px;
            background: #fff;
        }
        .grid {
            margin-top: 16px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        .item {
            border: 1px solid #d7dde6;
            border-radius: 8px;
            padding: 10px 12px;
            min-height: 64px;
        }
        .label {
            display: block;
            color: #566170;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .value {
            font-size: 14px;
            font-weight: 700;
            word-break: break-word;
        }
        .actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            padding: 0 20px 20px;
        }
        .btn {
            display: block;
            border: 1px solid #101010;
            border-radius: 8px;
            padding: 13px 14px;
            text-align: center;
            text-decoration: none;
            font-weight: 800;
            color: #101010;
            background: #fff;
        }
        .btn-primary {
            color: #fff;
            background: #101010;
        }
        @media (max-width: 520px) {
            .header { align-items: flex-start; flex-direction: column; }
            .grid, .actions { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/AGBClogo1.png');
        $logoB64 = file_exists($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
        $empresaNombre = trim((string) (optional($contrato->empresa)->nombre ?? optional(optional($contrato->user)->empresa)->nombre ?? ''));
    @endphp

    <main class="panel">
        <div class="header">
            @if($logoB64)
                <img class="logo" src="data:image/png;base64,{{ $logoB64 }}" alt="Correos de Bolivia">
            @endif
            <div>
                <h1 class="title">Guia verificada</h1>
                <p class="subtitle">QR cifrado de verificacion</p>
            </div>
        </div>

        <div class="body">
            <span class="code">{{ $contrato->codigo }}</span>

            <div class="grid">
                <div class="item">
                    <span class="label">Remitente</span>
                    <span class="value">{{ $contrato->nombre_r ?: '-' }}</span>
                </div>
                <div class="item">
                    <span class="label">Destinatario</span>
                    <span class="value">{{ $contrato->nombre_d ?: '-' }}</span>
                </div>
                <div class="item">
                    <span class="label">Origen</span>
                    <span class="value">{{ $contrato->origen ?: '-' }}</span>
                </div>
                <div class="item">
                    <span class="label">Destino</span>
                    <span class="value">{{ $contrato->destino ?: '-' }}</span>
                </div>
                <div class="item">
                    <span class="label">Empresa</span>
                    <span class="value">{{ $empresaNombre !== '' ? $empresaNombre : '-' }}</span>
                </div>
                <div class="item">
                    <span class="label">Fecha</span>
                    <span class="value">{{ optional($contrato->created_at)->format('d/m/Y H:i') ?: '-' }}</span>
                </div>
            </div>
        </div>

        <div class="actions">
            <a class="btn btn-primary" href="{{ $reimprimirUrl }}" target="_blank" rel="noopener">Reimprimir</a>
            <a class="btn" href="{{ $rastrearUrl }}" target="_blank" rel="noopener">Rastrear</a>
        </div>
    </main>
</body>
</html>
