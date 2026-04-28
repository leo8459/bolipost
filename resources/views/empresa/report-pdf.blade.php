<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Empresas</title>
    <style>
        @page { margin: 22px 24px; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10px;
            color: #1f2937;
        }

        .header {
            border-bottom: 2px solid #20539A;
            padding-bottom: 9px;
            margin-bottom: 14px;
        }

        .title {
            color: #20539A;
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .meta {
            width: 100%;
            border-collapse: collapse;
            color: #4b5563;
        }

        .meta td {
            padding: 2px 0;
        }

        table.empresas {
            width: 100%;
            border-collapse: collapse;
        }

        .empresas th,
        .empresas td {
            border: 1px solid #cbd5e1;
            padding: 5px 6px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .empresas th {
            background: #e8eef8;
            color: #1e3a6d;
            font-weight: bold;
            text-align: left;
        }

        .text-center { text-align: center; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Reporte de Empresas</div>
        <table class="meta">
            <tr>
                <td><strong>Fecha de generacion:</strong> {{ $generatedAt->format('d/m/Y H:i') }}</td>
                <td class="text-right"><strong>Total empresas:</strong> {{ $empresas->count() }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Orden:</strong> Codigo cliente ascendente | <strong>Excluye:</strong> codigo_cliente 9999</td>
            </tr>
        </table>
    </div>

    <table class="empresas">
        <thead>
            <tr>
                <th style="width: 7%;" class="text-center">#</th>
                <th style="width: 46%;">Nombre</th>
                <th style="width: 17%;">Sigla</th>
                <th style="width: 17%;">Codigo cliente</th>
                <th style="width: 13%;">Creado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($empresas as $index => $empresa)
                <tr>
                    <td class="text-center">{{ $index + 1 }}</td>
                    <td>{{ $empresa->nombre }}</td>
                    <td>{{ $empresa->sigla }}</td>
                    <td>{{ $empresa->codigo_cliente }}</td>
                    <td>{{ optional($empresa->created_at)->format('d/m/Y') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="text-center">No hay empresas para mostrar.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
