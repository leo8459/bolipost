<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie-11">
    <title>Formulario de Entrega</title>
    <style>
        * { margin: 0; padding: 0; }
        .center-text { text-align: center; }
        .small-text { font-size: 14px; }
        .special-text { text-align: center; font-size: 12px; }
        .normal-text { font-size: 12px; }
        .centro { margin-top: 0; margin-bottom: 0; margin-left: 12%; }
        table { width: 100%; }
        table td { width: 100%; }
        p { margin: 0; padding: 0; }
    </style>
</head>
<body>
@foreach ($packages as $package)
    <div class="container" style="margin-top: 24px;">
        <div class="modal-body">
            <div class="center-text">
                <h2 class="normal-text" style="margin-top: 0;">FORMULARIO DE ENTREGA</h2>
                <h3 class="normal-text">AGENCIA BOLIVIANA DE CORREOS</h3>
            </div>
            <table class="centro">
                <tr>
                    <td>
                        <p class="barcode">{!! DNS1D::getBarcodeHTML($package->codigo, 'C128', 1.25, 25) !!}</p>
                        <p class="small-text"><strong>Codigo Rastreo:</strong> {{ $package->codigo }}</p>
                        <p class="small-text"><strong>Destinatario:</strong> {{ $package->destinatario }}</p>
                        <p class="small-text"><strong>Ciudad:</strong> {{ $package->ciudad }}</p>
                        <p class="small-text"><strong>Ventanilla:</strong> {{ optional($package->ventanillaRef)->nombre_ventanilla ?? '-' }}</p>
                        <p class="small-text"><strong>Aduana:</strong> {{ $package->aduana }}</p>
                    </td>
                    <td>
                        <p class="small-text"><strong>Nro. Factura:</strong></p>
                        <p class="small-text"><strong>Usuario:</strong> {{ optional(auth()->user())->name }}</p>
                        <p class="small-text"><strong>Tipo:</strong> -</p>
                        <p class="small-text"><strong>Peso:</strong> {{ $package->peso }} gr.</p>
                        <p class="small-text"><strong>Entrega:</strong> {{ optional($package->estado)->nombre_estado }}</p>
                        <p class="small-text"><strong>Fecha Entrega:</strong> {{ now()->format('Y-m-d H:i') }}</p>
                    </td>
                </tr>
            </table>
            <br>
            <table>
                <tr>
                    <td>
                        <p class="special-text">__________________________</p>
                        <p class="special-text">RECIBIDO POR</p>
                        <p class="special-text">{{ $package->destinatario }}</p>
                    </td>
                    <td>
                        <p class="special-text">__________________________</p>
                        <p class="special-text">ENTREGADO POR</p>
                        <p class="special-text">{{ optional(auth()->user())->name }}</p>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    @if (strtoupper((string) $package->aduana) === 'SI')
        <div class="container" style="margin-top: 24px;">
            <div class="modal-body">
                <div class="center-text">
                    <h2 class="normal-text" style="margin-top: 0;">FORMULARIO DE ENTREGA</h2>
                    <h3 class="normal-text">AGENCIA BOLIVIANA DE CORREOS</h3>
                </div>
                <table class="centro">
                    <tr>
                        <td>
                            <p class="barcode">{!! DNS1D::getBarcodeHTML($package->codigo, 'C128', 1.25, 25) !!}</p>
                            <p class="small-text"><strong>Codigo Rastreo:</strong> {{ $package->codigo }}</p>
                            <p class="small-text"><strong>Destinatario:</strong> {{ $package->destinatario }}</p>
                            <p class="small-text"><strong>Ciudad:</strong> {{ $package->ciudad }}</p>
                            <p class="small-text"><strong>Ventanilla:</strong> {{ optional($package->ventanillaRef)->nombre_ventanilla ?? '-' }}</p>
                            <p class="small-text"><strong>Aduana:</strong> {{ $package->aduana }}</p>
                        </td>
                        <td>
                            <p class="small-text"><strong>Nro. Factura:</strong></p>
                            <p class="small-text"><strong>Usuario:</strong> {{ optional(auth()->user())->name }}</p>
                            <p class="small-text"><strong>Tipo:</strong> -</p>
                            <p class="small-text"><strong>Peso:</strong> {{ $package->peso }} gr.</p>
                            <p class="small-text"><strong>Entrega:</strong> {{ optional($package->estado)->nombre_estado }}</p>
                            <p class="small-text"><strong>Fecha Entrega:</strong> {{ now()->format('Y-m-d H:i') }}</p>
                        </td>
                    </tr>
                </table>
                <br>
                <table>
                    <tr>
                        <td>
                            <p class="special-text">__________________________</p>
                            <p class="special-text">RECIBIDO POR</p>
                            <p class="special-text">{{ $package->destinatario }}</p>
                        </td>
                        <td>
                            <p class="special-text">__________________________</p>
                            <p class="special-text">ENTREGADO POR</p>
                            <p class="special-text">{{ optional(auth()->user())->name }}</p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
    @endif
@endforeach
</body>
</html>
