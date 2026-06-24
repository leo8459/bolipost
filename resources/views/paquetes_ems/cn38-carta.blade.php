<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>CN-38 {{ $despacho }}</title>
    <style>
        @@page {
            size: letter portrait;
            margin: 24px 26px 28px 26px;
        }

        body {
            margin: 0;
            font-family: "Courier New", "DejaVu Sans Mono", monospace;
            font-size: 11px;
            line-height: 1.12;
            color: #7f95d1;
        }

        .sheet {
            width: 100%;
        }

        .top-note {
            font-size: 10px;
            margin-bottom: 4px;
        }

        .header-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .header-grid td {
            vertical-align: top;
            padding: 1px 4px 1px 0;
        }

        .center-title {
            text-align: center;
            font-size: 12px;
            letter-spacing: 0.4px;
        }

        .right-box {
            text-align: right;
            white-space: nowrap;
        }

        .block {
            margin-top: 10px;
        }

        .inline-label {
            display: inline-block;
            min-width: 148px;
        }

        .line {
            border-top: 1px dashed #b9c6eb;
            margin: 7px 0 6px;
        }

        table.list {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        table.list th,
        table.list td {
            padding: 1px 4px;
            font-weight: normal;
            text-align: left;
            vertical-align: top;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        table.list th {
            padding-bottom: 3px;
        }

        .c-despacho { width: 18%; }
        .c-orig { width: 8%; }
        .c-dest { width: 8%; }
        .c-cor { width: 7%; }
        .c-cp { width: 6%; }
        .c-ems { width: 7%; }
        .c-correos { width: 10%; }
        .c-endas { width: 10%; }
        .c-peso { width: 11%; text-align: right !important; }
        .c-obs { width: 15%; }

        .text-right {
            text-align: right !important;
        }

        .text-center {
            text-align: center !important;
        }

        .footer-box {
            margin-top: 26px;
        }

        .footer-grid {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .footer-grid td {
            padding: 2px 4px;
            vertical-align: top;
        }

        .stamp-box {
            height: 84px;
            position: relative;
        }

        .stamp {
            position: absolute;
            left: 0;
            bottom: 16px;
            font-size: 16px;
            letter-spacing: 1px;
            transform: rotate(-3deg);
        }

        .totals-line {
            border-top: 1px dashed #b9c6eb;
            margin-top: 10px;
            padding-top: 4px;
        }

        .tiny {
            font-size: 9px;
        }
    </style>
</head>
<body>
    @php
        $fecha = $generatedAt instanceof \Carbon\CarbonInterface
            ? $generatedAt
            : \Illuminate\Support\Carbon::parse($generatedAt);

        $origenCode = mb_strtoupper(mb_substr((string) $loggedInUserCity, 0, 3));
        $destinoCode = mb_strtoupper(mb_substr((string) $destinationCity, 0, 3));
    @endphp

    <div class="sheet">
        <div class="top-note">--&gt; AJ</div>

        <table class="header-grid">
            <tr>
                <td style="width:36%;">
                    <div>Admimistracion expedidora</div>
                    <div>BO - BOLIVIA</div>
                </td>
                <td style="width:44%;" class="center-title">
                    <div>FACTURA DE ENTREGA</div>
                    <div>{{ $fecha->format('d/m/Y') }}</div>
                </td>
                <td style="width:20%;" class="right-box">
                    <div>CN-38</div>
                    <div>Pagina&nbsp;&nbsp;1</div>
                </td>
            </tr>
        </table>

        <div class="block">
            <div><span class="inline-label">Oficina de cambio expedidora</span></div>
            <div>{{ $origenCode }} - {{ strtoupper((string) $loggedInUserCity) }}</div>
        </div>

        <div class="block" style="margin-top:6px;">
            <div><span class="inline-label">Oficina de destino</span></div>
            <div>{{ $destinoCode }} - {{ strtoupper((string) $destinationCity) }}</div>
        </div>

        <table class="header-grid block" style="margin-top:10px;">
            <tr>
                <td style="width:45%;">
                    <div><span class="inline-label">Itinerario</span></div>
                    <div>{{ $selectedTransport }}</div>
                </td>
                <td style="width:27%;">
                    <div><span class="inline-label">Apto. de transito</span></div>
                    <div>{{ strtoupper((string) $selectedTransport) }}</div>
                </td>
                <td style="width:28%;">
                    <div><span class="inline-label">Salida</span></div>
                    <div>{{ $fecha->format('d/m/Y') }}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <div>{{ $transportNumber }}</div>
                </td>
                <td>
                    <div><span class="inline-label">Apto. de descarga</span></div>
                    <div>{{ $destinoCode }}</div>
                </td>
                <td></td>
            </tr>
        </table>

        <div class="line"></div>

        <table class="list">
            <thead>
                <tr>
                    <th class="c-despacho">DESPACHO</th>
                    <th class="c-orig">ORIG.</th>
                    <th class="c-dest">DEST.</th>
                    <th class="c-cor text-center">COR.</th>
                    <th class="c-cp text-center">CP</th>
                    <th class="c-ems text-center">EMS</th>
                    <th class="c-correos text-center">CORRES.</th>
                    <th class="c-endas text-center">ENDAS.</th>
                    <th class="c-peso text-right">P E S O (Kg)</th>
                    <th class="c-obs">OBSERVACIONES</th>
                </tr>
            </thead>
        </table>

        <div class="line" style="margin-top:1px;"></div>

        <table class="list">
            <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td class="c-despacho">{{ strtoupper((string) ($row->despacho_etiqueta ?? $row->despacho ?? '-')) }}</td>
                        <td class="c-orig">{{ mb_strtoupper(mb_substr((string) ($row->origen ?? '-'), 0, 3)) }}</td>
                        <td class="c-dest">{{ mb_strtoupper(mb_substr((string) ($row->destino ?? '-'), 0, 3)) }}</td>
                        <td class="c-cor text-center"></td>
                        <td class="c-cp text-center"></td>
                        <td class="c-ems text-center">1</td>
                        <td class="c-correos text-center"></td>
                        <td class="c-endas text-center"></td>
                        <td class="c-peso text-right">{{ number_format((float) ($row->peso_total ?? 0), 1) }}</td>
                        <td class="c-obs"></td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="footer-box">
            <table class="footer-grid">
                <tr>
                    <td style="width:48%;" class="stamp-box">
                        <div class="stamp">EMS RECEPCION</div>
                    </td>
                    <td style="width:52%;"></td>
                </tr>
            </table>

            <div class="totals-line">
                <table class="list">
                    <tr>
                        <td style="width:44%;">T O T A L E S</td>
                        <td style="width:8%;" class="text-center">{{ $rows->count() }}</td>
                        <td style="width:8%;" class="text-center"></td>
                        <td style="width:10%;" class="text-center">0.0</td>
                        <td style="width:10%;" class="text-center">{{ $rows->count() }}</td>
                        <td style="width:10%;" class="text-center">0.0</td>
                        <td style="width:10%;" class="text-right">{{ number_format((float) $totalPeso, 1) }}</td>
                        <td style="width:4%;"></td>
                    </tr>
                </table>
            </div>

            <table class="footer-grid" style="margin-top:8px;">
                <tr>
                    <td style="width:50%;">
                        <div class="tiny">{{ strtoupper((string) $loggedUserName) }}</div>
                    </td>
                    <td style="width:50%; text-align:center;">
                        <div>OFICINA RECEPTORA</div>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
