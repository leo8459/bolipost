<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte de Envio EMS</title>
    <style>
        @page { margin: 20px; }
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #1f2937;
            font-size: 12px;
        }
        .header {
            background: #34447C;
            color: #fff;
            padding: 14px 16px;
            border-radius: 8px;
            margin-bottom: 14px;
        }
        .title {
            font-size: 18px;
            font-weight: 700;
            margin: 0 0 4px 0;
        }
        .subtitle {
            margin: 0;
            opacity: 0.95;
            font-size: 12px;
        }
        .meta {
            margin: 12px 0;
            padding: 10px 12px;
            background: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }
        .meta strong {
            color: #34447C;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th {
            background: #B99C46;
            color: #fff;
            text-align: left;
            padding: 8px;
            font-size: 11px;
            text-transform: uppercase;
        }
        td {
            border-bottom: 1px solid #e5e7eb;
            padding: 8px;
            font-size: 11px;
        }
        .footer {
            margin-top: 18px;
            font-size: 10px;
            color: #6b7280;
        }
        .firmas {
            margin-top: 34px;
            width: 100%;
        }
        .firma-box {
            width: 45%;
            display: inline-block;
            vertical-align: top;
            text-align: center;
        }
        .firma-linea {
            margin: 30px auto 8px auto;
            width: 85%;
            border-top: 1px solid #1f2937;
        }
        .firma-titulo {
            font-size: 11px;
            font-weight: 700;
            color: #34447C;
        }
        .firma-nombre {
            font-size: 11px;
            color: #1f2937;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Paquetes enviados a EMS</h1>
        <p class="subtitle">{{ $filtro ?? 'SELECCIONADOS' }}</p>
    </div>

    <div class="meta">
        <div><strong>Fecha y hora de generacion:</strong> {{ $generatedAt->format('d/m/Y H:i:s') }}</div>
        <div><strong>Total de paquetes enviados:</strong> {{ $paquetes->count() }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Codigo</th>
                <th>Usuario</th>
                <th>Fecha/Hora de registro</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($paquetes as $index => $paquete)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $paquete->codigo }}</td>
                    <td>{{ optional($paquete->user)->name ?? 'N/A' }}</td>
                    <td>{{ optional($paquete->created_at)->format('d/m/Y H:i:s') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="firmas">
        <div class="firma-box">
            <div class="firma-linea"></div>
            <div class="firma-titulo">Entregado por</div>
            <div class="firma-nombre">{{ $loggedUserName ?? 'Usuario del sistema' }}</div>
        </div>
        <div class="firma-box" style="float:right;">
            <div class="firma-linea"></div>
            <div class="firma-titulo">Recibi conforme</div>
            <div class="firma-nombre">__________________________</div>
        </div>
    </div>

    <div class="footer">
        Documento generado automaticamente por el sistema EMS.
    </div>
</body>
</html>
