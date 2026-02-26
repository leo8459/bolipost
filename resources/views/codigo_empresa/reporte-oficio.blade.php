<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Codigos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; padding: 18px; font-size: 11px; }
        .title {
            text-align: center;
            margin-bottom: 10px;
        }
        .title h2 { font-size: 15px; }
        .title p { font-size: 11px; margin-top: 3px; }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        th, td {
            border: 1px solid #444;
            padding: 4px 5px;
            word-wrap: break-word;
        }
        th {
            background: #f0f2f6;
            text-align: left;
            font-size: 10px;
        }
        td { font-size: 10px; }
    </style>
</head>
<body>
    <div class="title">
        <h2>REPORTE DE CODIGOS</h2>
        <p>
            Empresa:
            <strong>{{ $empresa ? ($empresa->nombre . ' (' . $empresa->sigla . ')') : 'TODAS' }}</strong> |
            Rango:
            <strong>{{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }}</strong> a
            <strong>{{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}</strong> |
            Fecha: <strong>{{ $generatedAt->format('d/m/Y H:i') }}</strong>
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:6%;">#</th>
                <th style="width:30%;">Empresa</th>
                <th style="width:14%;">Sigla</th>
                <th style="width:16%;">Cod. cliente</th>
                <th style="width:14%;">Total codigos</th>
                <th style="width:10%;">Primero</th>
                <th style="width:10%;">Ultimo</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resumen as $index => $fila)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $fila->nombre }}</td>
                    <td>{{ $fila->sigla }}</td>
                    <td>{{ $fila->codigo_cliente }}</td>
                    <td>{{ (int) $fila->total_codigos }}</td>
                    <td>{{ \Carbon\Carbon::parse($fila->primera_fecha)->format('d/m/y') }}</td>
                    <td>{{ \Carbon\Carbon::parse($fila->ultima_fecha)->format('d/m/y') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
