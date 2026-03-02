<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Despacho</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
        }
        th,
        td {
            border: 1px solid #000;
            padding: 5px;
        }
        .first-table th,
        .first-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            line-height: 0.95;
        }
        thead {
            background-color: #f2f2f2;
        }
        @page {
            size: landscape;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            line-height: 0.9;
            margin-top: -18px;
        }
        .title {
            text-align: center;
            margin-top: -26px;
        }
        .second-table {
            border: none;
            margin: 20px auto;
            line-height: 0;
        }
        .second-table th {
            background-color: white;
            border: none;
            padding: 5px;
            text-align: center;
            line-height: 0;
        }
        .second-table td {
            border: none;
            padding: 5px;
            text-align: center;
            line-height: 0;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            @if (file_exists(public_path('images/images.png')))
                <img src="{{ public_path('images/images.png') }}" alt="Logo" width="150" height="50">
            @endif
        </div>

        <div class="barcode-section" style="text-align: right; margin-top: -50px;">
            @php
                $barcodePng = null;
                try {
                    $barcodePng = DNS1D::getBarcodePNG($manifiesto, 'C128', 1.25, 25);
                } catch (\Throwable $e) {
                    $barcodePng = null;
                }
            @endphp

            @if($barcodePng)
                <img src="data:image/png;base64,{{ $barcodePng }}" alt="Codigo de barras" style="display: block; margin-bottom: 10px;">
            @endif

            <p style="margin: 5px 0; font-size: 15px; font-weight: bold;">{{ $manifiesto }}</p>
            <p style="margin: 5px 0; font-size: 18px;">CN 33</p>
        </div>

        <div class="title">
            <h2>Manifiesto Area Clasificacion</h2>
            <h3>AGENCIA BOLIVIANA DE CORREOS</h3>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th rowspan="2">Operadores</th>
                <th>Origen</th>
                <td colspan="5">{{ $ciudadOrigen }} - AGENCIA BOLIVIANA DE CORREOS</td>
            </tr>
            <tr>
                <th>Destino</th>
                <td colspan="5">{{ $ciudadDestino }} - AGENCIA BOLIVIANA DE CORREOS</td>
            </tr>
        </thead>
        <tbody>
            <tr>
                <th>Origen AE</th>
                <th>Destino OE</th>
                <th>Categoria</th>
                <th>Sub-Clase</th>
                <th>Año</th>
                <th>Despacho No</th>
                <th>Fecha</th>
            </tr>
            <tr>
                <td>{{ $siglasOrigen }}</td>
                <td>{{ $siglasDestino }}</td>
                <td>A</td>
                <td>UN</td>
                <td>{{ $anioPaquete }}</td>
                <td>{{ $manifiesto }}</td>
                <td>{{ now()->format('Y-m-d H:i') }}</td>
            </tr>
            <tr>
                <th colspan="2" rowspan="2">Nombre del Usuario:</th>
                <td colspan="5" rowspan="2">{{ optional(auth()->user())->name }}</td>
            </tr>
            <tr></tr>
        </tbody>
    </table>

    <br>

    <table class="first-table">
        <thead>
            <tr>
                <th>No</th>
                <th>Codigo Rastreo</th>
                <th>Destinatario</th>
                <th>Telefono</th>
                <th>Pais/Ciudad</th>
                <th>Ventanilla</th>
                <th>Peso (gr.)</th>
                <th>Aduana</th>
                <th>Fecha Ingreso</th>
            </tr>
        </thead>
        <tbody>
            @php $i = 1; @endphp
            @foreach ($packages as $package)
                <tr>
                    <td>{{ $i }}</td>
                    <td>{{ $package->codigo }}</td>
                    <td>{{ $package->destinatario }}</td>
                    <td>{{ $package->telefono }}</td>
                    <td>{{ $package->ciudad }}</td>
                    <td>{{ optional($package->ventanillaRef)->nombre_ventanilla ?? '-' }}</td>
                    <td>{{ number_format((float) $package->peso, 3) }} gr.</td>
                    <td>{{ $package->aduana }}</td>
                    <td>{{ optional($package->created_at)->format('Y-m-d H:i') }}</td>
                </tr>
                @php $i++; @endphp
            @endforeach
        </tbody>
    </table>

    <br>

    <div>
        <table class="second-table">
            <tr>
                <td>
                    <p>__________________________</p>
                    <p>RECIBIDO POR</p>
                </td>
                <td>
                    <p>__________________________ </p>
                    <p>ENTREGADO POR</p>
                    <p>{{ optional(auth()->user())->name }}</p>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
