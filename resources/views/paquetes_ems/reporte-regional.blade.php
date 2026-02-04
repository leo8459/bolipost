<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Manifiesto CN-33</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
        .header {
            width: 100%;
            margin-bottom: 15px;
        }
        .table-head {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }
        .table-head td {
            border: 1px solid #333;
            padding: 5px;
        }
        .title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
        }
        .sub-header {
            text-align: center;
        }
        .field {
            font-weight: bold;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .table th,
        .table td {
            border: 1px solid #333;
            padding: 5px;
            text-align: center;
        }
        .footer {
            margin-top: 24px;
            text-align: center;
        }
        .firmas {
            margin-top: 26px;
        }
        .firma {
            width: 46%;
            display: inline-block;
            text-align: center;
            vertical-align: top;
        }
        .linea {
            margin: 35px auto 6px auto;
            width: 80%;
            border-top: 1px solid #333;
        }
    </style>
</head>
<body>
    <div class="header">
        @if (file_exists(public_path('images/ems.png')))
            <img src="{{ public_path('images/ems.png') }}" alt="" width="150" height="50"><br>
        @endif

        <table class="table-head">
            <tr>
                <td colspan="4" class="title">PAQUETES ENVIADOS A EMS</td>
                <td rowspan="3" style="text-align: center; vertical-align: middle; width: 25%;">
                    <div>
                        <strong>{{ $currentManifiesto }}</strong>
                    </div>
                </td>
            </tr>
            <tr>
                <td colspan="2" class="sub-header">BO-BOLIVIA</td>
                <td colspan="2" class="sub-header">CN-33</td>
            </tr>
            <tr>
                <td class="field" style="width: 15%;">Oficina de Origen:</td>
                <td style="width: 25%;">{{ $loggedInUserCity }}</td>
                <td class="field" style="width: 15%;">Oficina de Destino:</td>
                <td style="width: 25%;">{{ $destinationCity }}</td>
            </tr>
            <tr>
                <td class="field">DESPACHO:</td>
                <td>{{ $currentManifiesto }}</td>
                <td class="field">Dia de Despacho:</td>
                <td>{{ $generatedAt->format('d/m/Y') }}</td>
                <td class="field">Hora: {{ $generatedAt->format('H:i') }}</td>
            </tr>
            <tr>
                <td class="field">PRIORITARIO:</td>
                <td>X</td>
                <td class="field">MODO:</td>
                <td colspan="2">{{ $selectedTransport }}</td>
            </tr>
            <tr>
                <td class="field">N de vuelo/Transporte:</td>
                <td colspan="4">{{ $numeroVuelo !== '' ? $numeroVuelo : '-' }}</td>
            </tr>
        </table>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>ENVIO</th>
                <th>ORIG.</th>
                <th>DEST.</th>
                <th>CANT.</th>
                <th>COR</th>
                <th>PESO</th>
                <th>REMITENTE</th>
                <th>ENDAS</th>
                <th>EMS</th>
                <th>OBSERVACION</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalCantidad = 0;
                $totalPeso = 0;
            @endphp

            @foreach ($paquetes as $paquete)
                @php
                    $cantidad = (int) ($paquete->cantidad ?? 1);
                    $peso = (float) ($paquete->peso ?? 0);
                    $totalCantidad += $cantidad;
                    $totalPeso += $peso;
                @endphp
                <tr>
                    <td>{{ $paquete->codigo }}</td>
                    <td>{{ $paquete->origen }}</td>
                    <td>{{ $destinationCity }}</td>
                    <td>{{ $cantidad }}</td>
                    <td></td>
                    <td>{{ number_format($peso, 3) }}</td>
                    <td>{{ $paquete->nombre_remitente }}</td>
                    <td></td>
                    <td>X</td>
                    <td></td>
                </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td><strong>TOTAL</strong></td>
                <td></td>
                <td></td>
                <td><strong>{{ $totalCantidad }}</strong></td>
                <td></td>
                <td><strong>{{ number_format($totalPeso, 3) }} Kg</strong></td>
                <td colspan="4"></td>
            </tr>
        </tfoot>
    </table>

    <div class="firmas">
        <div class="firma">
            <div class="linea"></div>
            <div><strong>Entregado por</strong></div>
            <div>{{ $loggedUserName }}</div>
        </div>
        <div class="firma" style="float: right;">
            <div class="linea"></div>
            <div><strong>Recibi conforme</strong></div>
            <div>__________________________</div>
        </div>
    </div>

    <div class="footer">
        Documento generado automaticamente por el sistema EMS.
    </div>
</body>
</html>
