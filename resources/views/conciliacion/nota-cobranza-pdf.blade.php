<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Nota de cobranza</title>
    <style>
        @page { size: letter; margin: 21mm 23mm 19mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 10.5px; line-height: 1.52; color: #222; }
        .header { width: 100%; margin-bottom: 16px; border-collapse: collapse; }
        .header td { vertical-align: middle; }
        .ministerio-logo { width: 215px; }
        .correo-logo { width: 150px; float: right; }
        .date { margin: 4px 0 8px; text-align: right; }
        .reference { margin: 0 0 15px; font-size: 9.5px; font-weight: bold; }
        .recipient { margin-bottom: 13px; line-height: 1.4; }
        .recipient strong { font-size: 11px; text-transform: uppercase; }
        .subject { margin: 0 0 15px; text-align: center; font-weight: bold; text-decoration: underline; }
        p { margin: 0 0 11px; text-align: justify; }
        .salutation { margin-bottom: 12px; }
        .detail { width: 100%; margin: 10px 0 14px; border-collapse: collapse; }
        .detail th, .detail td { padding: 6px 7px; border: 1px solid #4b5563; }
        .detail th { background: #e9edf2; font-size: 9px; text-align: center; text-transform: uppercase; }
        .detail td:last-child { text-align: right; white-space: nowrap; }
        .payment { padding: 9px 11px; border-left: 4px solid #d4a514; background: #fffaf0; }
        .closing { margin-top: 13px; }
        .footer { position: fixed; right: 0; bottom: -12mm; left: 0; padding-top: 5px; border-top: 1px solid #d7dce2; color: #555; font-size: 7.5px; text-align: right; }
        .footer-accent { position: fixed; bottom: -15mm; left: 0; width: 34%; height: 3px; background: #d93025; }
    </style>
</head>
<body>
    @php
        $ministerioPath = public_path('images/ministerio-obras-publicas.png');
        $correoPath = public_path('images/AGBClogo2.png');
        $ministerioLogo = is_file($ministerioPath) ? base64_encode(file_get_contents($ministerioPath)) : null;
        $correoLogo = is_file($correoPath) ? base64_encode(file_get_contents($correoPath)) : null;
        $periodo = mb_strtoupper($mesNombre).' GESTIÓN '.$conciliacion->anio;
        $correoContacto = $conciliacion->formato_nota_cobranza === 'cuenta_personal'
            ? 'roger.gonzales@correos.gob.bo'
            : 'claudia.riveros@correos.gob.bo';
    @endphp

    <div class="footer">
        CORREOS DE BOLIVIA · Zona Central - Av. Mariscal Santa Cruz, Esq. Calle Oruro, Edif. Centro de Comunicaciones<br>
        Teléfono: +591 (2) 2152423
    </div>
    <div class="footer-accent"></div>

    <table class="header">
        <tr>
            <td>
                @if($ministerioLogo)<img class="ministerio-logo" src="data:image/png;base64,{{ $ministerioLogo }}" alt="Ministerio de Obras Públicas, Servicios y Vivienda">@endif
            </td>
            <td>
                @if($correoLogo)<img class="correo-logo" src="data:image/png;base64,{{ $correoLogo }}" alt="Correos de Bolivia">@endif
            </td>
        </tr>
    </table>

    <div class="date">La Paz, {{ $fecha->translatedFormat('d \d\e F \d\e Y') }}</div>
    <div class="reference">NC-{{ $conciliacion->anio }}-{{ str_pad((string) $conciliacion->id, 5, '0', STR_PAD_LEFT) }}</div>

    <div class="recipient">
        Señores<br>
        <strong>{{ $conciliacion->nombre_empresa_cobranza }}</strong><br>
        Presente
    </div>

    <div class="subject">REF.: NOTA DE COBRANZA - PERIODO {{ $periodo }}</div>
    <p class="salutation">De mi consideración:</p>

    <p>
        De acuerdo con el estado de cuenta del mes de {{ mb_strtolower($mesNombre) }} de la gestión {{ $conciliacion->anio }},
        remitido por la Dirección de Operaciones de nuestra entidad, la deuda de su Institución asciende a
        <strong>Bs {{ number_format($monto, 2, ',', '.') }}</strong>
        ({{ $montoLiteral }}), conforme al siguiente detalle:
    </p>

    <table class="detail">
        <thead>
            <tr>
                <th style="width:62%">Concepto</th>
                <th style="width:20%">Periodo</th>
                <th style="width:18%">Monto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $conciliacion->factura_descripcion ?: 'Servicios de correo y courier por contrato' }}</td>
                <td style="text-align:center">{{ $mesNombre }} {{ $conciliacion->anio }}</td>
                <td>Bs {{ number_format($monto, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @if($conciliacion->formato_nota_cobranza === 'cuenta_personal')
        <p class="payment">
            Los servicios prestados por la Agencia Boliviana de Correos deben ser cancelados a la
            <strong>Cuenta 1-0000004800880 del Banco Unión S.A.</strong>, a nombre de la funcionaria
            <strong>Claudia Patricia Riveros Vera</strong>, Profesional III de Tesorería, Facturación y Cobranzas,
            facultada para esta operación mediante Memorándum AGBC/DESP/DGE No. 036/2025.
        </p>
    @else
        <p class="payment">
            Los servicios prestados por la Agencia Boliviana de Correos deben ser cancelados al Banco Central de Bolivia
            mediante pago PECC a la <strong>Cuenta Única del Tesoro CUT No. 3987069001, Libreta 00383012001</strong>,
            denominada Ingresos Agencia Boliviana de Correos. Para transferencias electrónicas vía SIGEP,
            la Agencia Boliviana de Correos se encuentra registrada con el Número de Documento <strong>355701027</strong>.
        </p>
    @endif

    <p>
        Agradeceremos detallar en el comprobante de pago el nombre de la empresa depositante y el periodo cancelado.
        Después de efectivizar el depósito, deberán remitir el comprobante o reporte de transferencia a los correos
        electrónicos {{ $correoContacto }} y abel.rojas@correos.gob.bo, así como físicamente a nuestras oficinas
        centrales ubicadas en Av. Mariscal Santa Cruz esquina calle Oruro No. 1240, Edif. Centro de Comunicaciones,
        La Paz - Mezanine.
    </p>

    <p class="closing">
        Sin otro particular y agradeciendo la confianza depositada en la Agencia Boliviana de Correos,
        saludamos a ustedes con las consideraciones más distinguidas.
    </p>

</body>
</html>
