<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Etiquetas de Codigos</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; padding: 14px; font-size: 11px; }
        .meta {
            margin-bottom: 8px;
            font-size: 10px;
            color: #111;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        td {
            width: 25%;
            height: 72px;
            text-align: center;
            vertical-align: middle;
            border-right: 1px dashed #9aa0a6;
            border-bottom: 1px dashed #9aa0a6;
            padding: 5px 4px;
        }
        tr td:last-child { border-right: 0; }
        .barcode {
            margin-bottom: 3px;
        }
        .barcode img{
            width: 94%;
            height: 26px;
            object-fit: contain;
        }
        .codigo {
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.2px;
        }
    </style>
</head>
<body>
    <div class="meta">
        <strong>Empresa:</strong> {{ $empresa->nombre }} ({{ $empresa->sigla }}) |
        <strong>Fecha:</strong> {{ $generatedAt->format('d/m/Y H:i') }} |
        <strong>Total codigos:</strong> {{ count($codigos) }}
    </div>

    <table>
        <tbody>
            @foreach ($codigos as $codigo)
                @php
                    $barcodePng = DNS1D::getBarcodePNG($codigo, 'C128', 1.5, 42);
                @endphp
                <tr>
                    @for ($i = 0; $i < 4; $i++)
                        <td>
                            <div class="barcode">
                                <img src="data:image/png;base64,{{ $barcodePng }}" alt="{{ $codigo }}">
                            </div>
                            <div class="codigo">{{ $codigo }}</div>
                        </td>
                    @endfor
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
