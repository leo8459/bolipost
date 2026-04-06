<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Reencaminado Certificados</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 4px; vertical-align: middle; }
        th { font-weight: 800; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .header { width: 100%; margin-bottom: 10px; }
        .header td { border: 0; vertical-align: top; }
        .title-main { margin: 0; font-size: 26px; font-weight: 800; line-height: 1.05; }
        .title-sub { margin: 4px 0 0 0; font-size: 18px; font-weight: 800; line-height: 1.1; }
        .report-code { text-align: right; font-size: 22px; font-weight: 800; line-height: 1; margin-bottom: 6px; }
        .section-space { margin-top: 14px; }
        .no-border { border: 0 !important; }
        .special-text { text-align: center; font-size: 11px; line-height: 1.1; border: 0; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td style="width: 30%;">
                <img src="{{ public_path('images/images.png') }}" alt="" width="160" height="55">
            </td>
            <td style="width: 40%;" class="text-center">
                <h2 class="title-main">Reporte de Reencaminado</h2>
                <h3 class="title-sub">Paquetes Certificados</h3>
                <div><strong>CORREOS DE BOLIVIA</strong></div>
            </td>
            <td style="width: 30%;" class="text-center">
                <div class="report-code">RPT</div>
                <div>{{ optional($generatedAt)->format('d/m/Y H:i') }}</div>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <th style="width: 20%;">Generado por</th>
            <td style="width: 30%;">{{ $generatedBy !== '' ? $generatedBy : 'N/A' }}</td>
            <th style="width: 20%;">Fecha</th>
            <td style="width: 30%;">{{ optional($generatedAt)->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <th>Total de paquetes</th>
            <td>{{ $packages->count() }}</td>
            <th>Tipo</th>
            <td>Certificados</td>
        </tr>
    </table>

    <div class="section-space"><strong>Detalle de paquetes reencaminados</strong></div>
    <table>
        <thead>
            <tr>
                <th style="width: 12%;">Codigo</th>
                <th>Destinatario</th>
                <th style="width: 12%;">Telefono</th>
                <th style="width: 12%;">Ciudad origen</th>
                <th style="width: 12%;">Ciudad destino</th>
                <th style="width: 10%;">Zona</th>
                <th style="width: 14%;">Ventanilla</th>
                <th style="width: 12%;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($packages as $package)
                <tr>
                    <td>{{ $package->codigo }}</td>
                    <td>{{ $package->destinatario }}</td>
                    <td>{{ $package->telefono }}</td>
                    <td>{{ $package->ciudad_origen ?? '-' }}</td>
                    <td>{{ $package->ciudad_destino ?? '-' }}</td>
                    <td>{{ $package->zona ?? '-' }}</td>
                    <td>{{ optional($package->ventanillaRef)->nombre_ventanilla ?? ($package->ventanilla ?? '-') }}</td>
                    <td>{{ optional($package->estado)->nombre_estado ?? '-' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table style="margin-top: 50px;">
        <tr>
            <td class="special-text no-border">
                __________________________<br>
                RECIBIDO POR
            </td>
            <td class="special-text no-border">
                __________________________<br>
                ENTREGADO POR<br>
                {{ $generatedBy !== '' ? $generatedBy : 'N/A' }}
            </td>
        </tr>
    </table>
</body>
</html>
