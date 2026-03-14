<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Reencaminado Certificados</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1f2937; }
        h1 { margin: 0 0 6px; font-size: 18px; }
        .meta { margin-bottom: 14px; font-size: 10px; color: #4b5563; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #e5eefb; color: #1d4f91; font-weight: bold; }
    </style>
</head>
<body>
    <h1>Reporte de Reencaminado - Paquetes Certificados</h1>
    <div class="meta">
        Generado por: {{ $generatedBy !== '' ? $generatedBy : 'N/A' }} |
        Fecha: {{ optional($generatedAt)->format('d/m/Y H:i') }} |
        Total: {{ $packages->count() }}
    </div>

    <table>
        <thead>
            <tr>
                <th>Codigo</th>
                <th>Destinatario</th>
                <th>Telefono</th>
                <th>Ciudad origen</th>
                <th>Ciudad destino</th>
                <th>Zona</th>
                <th>Ventanilla</th>
                <th>Estado</th>
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
</body>
</html>
