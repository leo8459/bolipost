<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie-11">
    <title>Lista de Rezago</title>
    <style>
        * { margin: 0; padding: 0; }
        .title { text-align: center; font-size: 14px; margin-bottom: 10px; }
        body { padding: 30px 30px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 4px 6px; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
    <div class="title">
        <strong>LISTA DE REZAGO</strong><br>
        <span>Fecha: {{ now()->format('Y-m-d H:i') }}</span>
    </div>

    <table>
        <thead>
            <tr>
                <th>Codigo</th>
                <th>Destinatario</th>
                <th>Telefono</th>
                <th>Ciudad</th>
                <th>Zona</th>
                <th>Ventanilla</th>
                <th>Peso</th>
                <th>Tipo</th>
                <th>Aduana</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($packages as $package)
                <tr>
                    <td>{{ $package->codigo }}</td>
                    <td>{{ $package->destinatario }}</td>
                    <td>{{ $package->telefono }}</td>
                    <td>{{ $package->cuidad }}</td>
                    <td>{{ $package->zona }}</td>
                    <td>{{ optional($package->ventanillaRef)->nombre_ventanilla ?? $package->ventanilla }}</td>
                    <td>{{ $package->peso }}</td>
                    <td>{{ $package->tipo }}</td>
                    <td>{{ $package->aduana }}</td>
                    <td>{{ optional($package->estado)->nombre_estado }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
