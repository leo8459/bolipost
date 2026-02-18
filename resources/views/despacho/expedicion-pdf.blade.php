<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CN-35, CN-31 y CN-38</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { border: 1px solid #000; padding: 4px; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .header { width: 100%; margin-bottom: 10px; }
        .header td { border: 0; vertical-align: top; }
        .cn-code-right { text-align: right; font-size: 24px; font-weight: 800; line-height: 1; margin-bottom: 6px; }
        .title-main { margin: 0; font-size: 34px; font-weight: 800; line-height: 1.05; }
        .title-sub { margin: 4px 0 0 0; font-size: 24px; font-weight: 800; line-height: 1.1; }
        .special-text { text-align: center; font-size: 11px; line-height: 1.1; border: 0; }
        .section-space { margin-top: 14px; }
        .cn35-table { width: 64%; margin-bottom: 12px; }
        .cn35-block { page-break-inside: avoid; break-inside: avoid; margin-bottom: 10px; }
        .cn35-table, .cn35-table tr, .cn35-table td, .cn35-table th { page-break-inside: avoid; break-inside: avoid; }
        .no-border { border: 0 !important; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    @php
        $subclaseTranslation = [
            'A' => 'Aereo',
            'B' => 'S.A.L.',
            'C' => 'Superficie',
            'D' => 'Prioritario por superficie',
        ];
    @endphp

    <table class="header">
        <tr>
            <td style="width: 30%;">
                <img src="{{ public_path('images/images.png') }}" alt="" width="160" height="55">
            </td>
            <td style="width: 40%;" class="text-center">
                <h2 class="title-main">Hoja de Aviso</h2>
                <h3 class="title-sub">AGENCIA BOLIVIANA DE CORREOS</h3>
            </td>
            <td style="width: 30%;" class="text-center">
                <div class="cn-code-right">CN 31</div>
                @if(class_exists('\DNS1D'))
                    {!! DNS1D::getBarcodeHTML($identificador, 'C128', 1.25, 25) !!}
                @endif
                <div>{{ $identificador }}</div>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <th style="width: 20%;">Operadores</th>
            <th style="width: 15%;">Origen</th>
            <td colspan="5">{{ $ciudadOrigen }} - AGENCIA BOLIVIANA DE CORREOS</td>
        </tr>
        <tr>
            <th></th>
            <th>Destino</th>
            <td colspan="5">{{ $ciudadDestino }} - AGENCIA BOLIVIANA DE CORREOS</td>
        </tr>
        <tr>
            <th>Origen OE</th>
            <th>Destino OE</th>
            <th>Categoria</th>
            <th>Sub-Clase</th>
            <th>AÃ±o</th>
            <th>Nro. de Despacho</th>
            <th>Fecha</th>
        </tr>
        <tr>
            <td>{{ $siglaOrigen }}</td>
            <td>{{ $ofdestino }}</td>
            <td>{{ $categoria }}</td>
            <td>{{ $subclase }}</td>
            <td>{{ $ano }}</td>
            <td>{{ str_pad((string) $despacho->nro_despacho, 3, '0', STR_PAD_LEFT) }}</td>
            <td>{{ $created_at }}</td>
        </tr>
    </table>

    <div class="section-space"><strong>1. Cantidad de sacas</strong></div>
    <table>
        <tr>
            <th>Declaracion de envases</th>
            <th>Sacas</th>
            <th>Total</th>
        </tr>
        <tr>
            <td>Envases en el despacho</td>
            <td>{{ $totalContenido }}</td>
            <td>{{ $totalContenido }}</td>
        </tr>
    </table>

    <div class="section-space"><strong>2. Gastos de transito y gastos terminales</strong></div>
    <table>
        <tr>
            <th colspan="3">Correo sujeto al pago de gastos terminales, totales por formato</th>
        </tr>
        <tr>
            <th></th>
            <th>Cantidad (PAQUETES)</th>
            <th>Peso (Kg.)</th>
        </tr>
        <tr>
            <td>Total</td>
            <td>{{ $totalPaquetes }}</td>
            <td>{{ $peso }}</td>
        </tr>
    </table>

    <table style="margin-top: 50px;">
        <tr>
            <td class="special-text no-border">
                __________________________<br>
                RECIBIDO POR
            </td>
            <td class="special-text no-border">
                __________________________<br>
                ENTREGADO POR<br>
                {{ auth()->user()->name }}
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    <table class="header">
        <tr>
            <td style="width: 35%;">
                <img src="{{ public_path('images/images.png') }}" alt="" width="140" height="45">
                <div class="text-right"><strong>CN 38</strong></div>
            </td>
            <td style="width: 65%;" class="text-center">
                <h3 style="margin:0;">Factura de Entrega</h3>
                <div>AGENCIA BOLIVIANA DE CORREOS</div>
            </td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="no-border">Oficina de Cambio: {{ $ciudadOrigen }} - {{ $siglaOrigenIata }}</td>
            <td class="no-border">Oficina de Destino: {{ $ciudadDestino }} - {{ $siglaDestinoIata }}</td>
            <td class="no-border">Medio de Transporte: {{ $subclaseTranslation[$categoria] ?? $categoria }}</td>
        </tr>
        <tr>
            <td class="no-border">Fecha: {{ optional($despacho->created_at)->format('Y-m-d') }}</td>
            <td class="no-border" colspan="2">Clase: {{ $subclase }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <th>No</th>
            <th>DESPACHO</th>
            <th>ORIGEN</th>
            <th>DESTINO</th>
            <th>BOLSAS DE CORREO</th>
            <th>PESO TOTAL (Kg.)</th>
            <th>OBSERVACIONES</th>
        </tr>
        @php $i = 1; @endphp
        @foreach ($sacas as $saca)
            <tr>
                <td>{{ $i }}</td>
                <td>{{ str_pad((string) $despacho->nro_despacho, 3, '0', STR_PAD_LEFT) }} / {{ str_pad((string) $saca->nro_saca, 3, '0', STR_PAD_LEFT) }} @if($loop->last)<strong>F</strong>@endif</td>
                <td>{{ $siglaOrigenIata }}</td>
                <td>{{ $siglaDestinoIata }}</td>
                <td class="text-center">1</td>
                <td>{{ $saca->peso }}</td>
                <td>{{ $saca->receptaculo }}</td>
            </tr>
            @php $i++; @endphp
        @endforeach
        @for ($j = $i; $j <= 20; $j++)
            <tr>
                <td>{{ $j }}</td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
                <td></td>
            </tr>
        @endfor
    </table>

    <table style="margin-top: 10px;">
        <tr>
            <td>SACAS TOTAL</td>
            <th>{{ $sacas->count() }}</th>
            <td>PESO TOTAL</td>
            <th>{{ $peso }} Kg.</th>
        </tr>
    </table>

    <table style="margin-top: 50px;">
        <tr>
            <td class="special-text no-border">
                __________________________<br>
                ENTREGADO POR<br>
                {{ auth()->user()->name }}
            </td>
            <td class="special-text no-border">
                __________________________<br>
                RECIBIDO POR
            </td>
        </tr>
    </table>

    <div class="page-break"></div>

    @foreach ($sacas as $saca)
        <div class="cn35-block">
        <table class="cn35-table">
            <tr>
                <td colspan="2">PARA :</td>
                <td class="text-center">GESPA</td>
                <td class="text-center">CN 35</td>
            </tr>
            <tr>
                <td colspan="2" class="text-center">{{ $ciudadDestino }}</td>
                <td class="text-center"><strong style="font-size: 16px;">{{ $subclase }}</strong></td>
                <td class="text-center">@if($loop->last)<strong>F</strong>@endif</td>
            </tr>
            <tr>
                <td>Cat: {{ $categoria }}</td>
                <td>SubC: {{ $subclase }}</td>
                <td class="text-right">{{ $siglaOrigen }} (BOA)</td>
                <td>{{ $ciudadOrigen }}</td>
            </tr>
            <tr>
                <td colspan="2" class="text-center">{{ optional($despacho->created_at)->format('Y-m-d') }}</td>
                <td colspan="2" class="text-center">AGENCIA BOLIVIANA DE CORREOS</td>
            </tr>
            <tr>
                <td>DespNo: {{ str_pad((string) $despacho->nro_despacho, 3, '0', STR_PAD_LEFT) }}</td>
                <td>RecNo: {{ str_pad((string) $saca->nro_saca, 3, '0', STR_PAD_LEFT) }}</td>
                <td colspan="2" class="text-center">(AGBC)</td>
            </tr>
            <tr>
                <td>Peso: {{ $saca->peso }} Kg.</td>
                <td>NoPaq: {{ $saca->paquetes }}</td>
                <td colspan="2" class="text-center">{{ $saca->receptaculo }}</td>
            </tr>
            <tr>
                <td>Via: {{ $subclaseTranslation[$categoria] ?? $categoria }}</td>
                <td></td>
                <td colspan="2">
                    @if(class_exists('\DNS1D'))
                        {!! DNS1D::getBarcodeHTML($saca->receptaculo, 'C128', 1.08, 40) !!}
                    @endif
                </td>
            </tr>
        </table>
        </div>
    @endforeach
</body>
</html>

