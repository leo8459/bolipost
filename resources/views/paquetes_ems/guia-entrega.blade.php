<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guia de Entrega EMS</title>
    <style>
        * { margin: 0; padding: 0; }
        body { font-family: DejaVu Sans, sans-serif; }
        .container { margin-top: 22px; }
        .center-text { text-align: center; }
        .small-text { font-size: 12px; line-height: 1.35; }
        .normal-text { font-size: 13px; }
        .special-text { text-align: center; font-size: 11px; }
        .content-table { width: 100%; margin-top: 6px; }
        .content-table td { width: 50%; vertical-align: top; }
        .signature-table { width: 100%; margin-top: 16px; }
        .signature-table td { width: 50%; }
        .barcode { margin: 6px 0; }
        .description-box {
            margin-top: 8px;
            border: 1px solid #000;
            min-height: 32px;
            padding: 4px 6px;
            font-size: 11px;
        }
    </style>
</head>
<body>
@foreach ($paquetes as $paquete)
    <div class="container">
        <div class="center-text">
            <h2 class="normal-text">GUIA DE ENTREGA EMS</h2>
            <h3 class="normal-text">AGENCIA BOLIVIANA DE CORREOS</h3>
        </div>

        <table class="content-table">
            <tr>
                <td>
                    <p class="barcode">{!! DNS1D::getBarcodeHTML($paquete->codigo, 'C128', 1.25, 25) !!}</p>
                    <p class="small-text"><strong>Codigo rastreo:</strong> {{ $paquete->codigo }}</p>
                    <p class="small-text"><strong>Destinatario:</strong> {{ $paquete->nombre_destinatario }}</p>
                    <p class="small-text"><strong>Telefono:</strong> {{ $paquete->telefono_destinatario }}</p>
                    <p class="small-text"><strong>Ciudad destino:</strong> {{ $paquete->ciudad }}</p>
                    <p class="small-text"><strong>Direccion:</strong> {{ $paquete->direccion }}</p>
                </td>
                <td>
                    <p class="small-text"><strong>Recibido por:</strong> {{ $recibidoPor }}</p>
                    <p class="small-text"><strong>Entregado por:</strong> {{ $loggedUserName }}</p>
                    <p class="small-text"><strong>Ciudad origen:</strong> {{ $loggedInUserCity }}</p>
                    <p class="small-text"><strong>Peso:</strong> {{ $paquete->peso }}</p>
                    <p class="small-text"><strong>Estado entrega:</strong> {{ $estadoEntrega }}</p>
                    <p class="small-text"><strong>Fecha entrega:</strong> {{ $generatedAt->format('Y-m-d H:i') }}</p>
                </td>
            </tr>
        </table>

        <div class="description-box">
            <strong>Descripcion:</strong> {{ $descripcion !== '' ? $descripcion : '-' }}
        </div>

        <table class="signature-table">
            <tr>
                <td>
                    <p class="special-text">__________________________</p>
                    <p class="special-text">RECIBIDO POR</p>
                    <p class="special-text">{{ $recibidoPor }}</p>
                </td>
                <td>
                    <p class="special-text">__________________________</p>
                    <p class="special-text">ENTREGADO POR</p>
                    <p class="special-text">{{ $loggedUserName }}</p>
                </td>
            </tr>
        </table>
    </div>
@endforeach
</body>
</html>
