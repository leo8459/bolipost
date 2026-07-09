<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Acta de conformidad de mantenimiento</title>
    <style>
        @php
            $logoLeftPath = public_path('images/LOGO-BOLIVIA.png');
            $logoLeftData = file_exists($logoLeftPath)
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoLeftPath))
                : null;

            $brand = trim((string) ($vehicle->brand?->nombre ?? $vehicle->marca ?? ''));
            $model = trim((string) ($vehicle->modelo ?? ''));
            $plate = trim((string) ($vehicle->placa ?? ''));
            $chassis = trim((string) ($vehicleChasis ?? $vehicle->chasis ?? ''));
            $engine = trim((string) ($vehicleMotor ?? $vehicle->motor ?? ''));
            $provider = $providerName !== '' ? $providerName : '____________________________';
            $providerCiLabel = $providerCi !== '' ? $providerCi : '______________';
            $cargoLabel = $inspectorRole !== '' ? $inspectorRole : '';
        @endphp

        @page {
            margin: 34px 34px 34px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #2d2d2d;
            font-size: 14px;
            line-height: 1.18;
            margin: 0;
        }

        .sheet {
            width: 74%;
            margin: 0 auto;
        }

        .header {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header td {
            vertical-align: top;
        }

        .header-left {
            width: 52%;
        }

        .header-right {
            width: 48%;
            text-align: right;
        }

        .left-wrap {
            width: 100%;
        }

        .left-wrap td {
            vertical-align: top;
        }

        .state-logo {
            width: 76px;
            padding-top: 1px;
        }

        .state-logo img {
            width: 68px;
            height: auto;
            opacity: .9;
        }

        .state-copy {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: .04em;
            line-height: 1.2;
            padding-top: 11px;
        }

        .brand-mark {
            display: inline-block;
            margin-top: 12px;
            color: #2e2e2e;
            font-style: italic;
            font-weight: bold;
            line-height: .9;
            text-align: center;
        }

        .brand-mark .mail {
            display: block;
            font-size: 15px;
            line-height: .7;
            text-align: right;
            padding-right: 8px;
        }

        .brand-mark .main {
            display: block;
            font-size: 30px;
        }

        .brand-mark .sub {
            display: block;
            font-size: 20px;
        }

        .title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            text-decoration: underline;
            margin: 26px 0 28px;
            line-height: 1.12;
        }

        .content {
            width: 100%;
            margin: 0 auto;
        }

        p {
            text-align: justify;
            margin: 0 0 22px;
        }

        .inline-line {
            display: inline-block;
            border-bottom: 1px solid #6b6b6b;
            min-width: 138px;
            height: 14px;
            vertical-align: baseline;
            text-align: center;
            padding: 0 4px;
        }

        .inline-line.sm {
            min-width: 96px;
        }

        .inline-line.md {
            min-width: 140px;
        }

        .inline-line.lg {
            min-width: 198px;
        }

        .signatures {
            width: 100%;
            margin: 188px auto 0;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .signatures td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            font-size: 13px;
        }

        .signature-block {
            white-space: pre-line;
        }

        .cargo-line {
            display: inline-block;
            min-width: 170px;
            border-bottom: 1px solid #6b6b6b;
            height: 14px;
            text-align: center;
            vertical-align: bottom;
            padding: 0 4px;
        }
    </style>
</head>
<body>
    <div class="sheet">
        <table class="header">
            <tr>
                <td class="header-left">
                    <table class="left-wrap">
                        <tr>
                            <td class="state-logo">
                                @if($logoLeftData)
                                    <img src="{{ $logoLeftData }}" alt="Estado Plurinacional de Bolivia">
                                @endif
                            </td>
                            <td class="state-copy">
                                MINISTERIO DE<br>
                                OBRAS PUBLICAS,<br>
                                SERVICIOS Y VIVIENDA
                            </td>
                        </tr>
                    </table>
                </td>
                <td class="header-right">
                    <div class="brand-mark" aria-label="Correos de Bolivia">
                        <span class="mail">////</span>
                        <span class="main">CORREOS</span>
                        <span class="sub">DE BOLIVIA</span>
                    </div>
                </td>
            </tr>
        </table>

        <div class="title">
            ACTA DE CONFORMIDAD DE SERVICIO DE<br>
            MANTENIMIENTO DE MOTOCICLETA
        </div>

        <div class="content">
            <p>
                En la ciudad de {{ $city }}, en fecha {{ $serviceDates }}, se deja constancia mediante la presente acta
                que el servicio de mantenimiento de la motocicleta perteneciente a Correos de Bolivia ha sido ejecutado por el
                proveedor {{ $provider }}, quien, al no contar con NIT ni emitir factura por el servicio prestado, autoriza
                expresamente a Correos de Bolivia a efectuar las retenciones de ley correspondientes conforme a la normativa vigente.
            </p>

            <p>
                La motocicleta, cuyas caracteristicas son: marca <span class="inline-line lg">{{ $brand }}</span>,
                modelo <span class="inline-line sm">{{ $model }}</span>,<br>
                placa <span class="inline-line md">{{ $plate }}</span>,
                numero de chasis <span class="inline-line lg">@if($chassis !== ''){{ $chassis }}@else&nbsp;@endif</span><br>
                y numero de motor <span class="inline-line lg">@if($engine !== ''){{ $engine }}@else&nbsp;@endif</span> fue sometida a mantenimiento
                que comprendio revision mecanica, ajustes necesarios, {{ $serviceDescription }} y trabajos requeridos para su
                correcto funcionamiento.
            </p>

            <p>
                Luego de realizada la inspeccion correspondiente por mi persona, se verifica que el servicio fue ejecutado
                de manera satisfactoria, cumpliendo con las condiciones y requerimientos tecnicos establecidos, encontrandose
                la motocicleta en optimas condiciones de funcionamiento.
            </p>

            <p>
                En consecuencia, se otorga la conformidad al servicio prestado, dejando constancia suscribe la presente
                Acta al pie de la misma.
            </p>
        </div>

        <table class="signatures">
            <tr>
                <td>
                    <div class="signature-block">{{ $providerName !== '' ? $providerName : '____________________________' }}
Por el Proveedor:
C.I: {{ $providerCiLabel }}</div>
                </td>
                <td>
                    <div class="signature-block">Sellos y Firma</div>
                    <div style="margin-top: 6px;">Cargo: <span class="cargo-line">{{ $cargoLabel }}</span></div>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
