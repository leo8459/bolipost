<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte ejecutivo de flujo de paquetería</title>
    <style>
        @page { margin: 15mm 13mm 17mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color:#172033; font-family:DejaVu Sans,sans-serif; font-size:9px; line-height:1.4; }
        .header, .meta, .kpis, .sheet, .leaders { border-collapse:collapse; width:100%; }
        .header { margin-bottom:12px; }
        .header td { vertical-align:middle; }
        .logo { max-height:47px; width:138px; }
        .institution { color:#31547d; font-size:8px; letter-spacing:.35px; text-align:right; text-transform:uppercase; }
        .institution strong { color:#153f72; display:block; font-size:10.5px; }
        .title-block { background:#123f73; border-left:5px solid #f5b800; color:#fff; padding:12px 15px; }
        .title { font-size:18px; letter-spacing:.25px; margin:0 0 3px; text-transform:uppercase; }
        .subtitle { color:#dce9f6; font-size:9.5px; }
        .meta { margin:9px 0; }
        .meta td { background:#f7f9fc; border:1px solid #d7e0e9; padding:6px 8px; width:33.33%; }
        .label { color:#64748b; display:block; font-size:7px; font-weight:bold; margin-bottom:2px; text-transform:uppercase; }
        .scope-note { background:#fff9e6; border:1px solid #efd47b; border-left:4px solid #f5b800; color:#5f5230; margin:0 0 10px; padding:7px 9px; }
        .kpis { border-collapse:separate; border-spacing:4px 0; margin:0 -4px 9px; width:calc(100% + 8px); }
        .kpis td { border:1px solid #d7e0e9; border-top:3px solid #f5b800; padding:7px 8px; vertical-align:top; width:20%; }
        .service-details { border-collapse:collapse; width:100%; }
        .service-panel { vertical-align:top; width:49%; padding:0; }
        .service-gap { width:2%; padding:0; }
        .consolidated-section { page-break-inside:avoid; }
        .metric-value { color:#123f73; display:block; font-size:13px; font-weight:bold; margin-top:3px; }
        .metric-note { color:#64748b; display:block; font-size:7px; margin-top:2px; }
        .section-title { border-bottom:2px solid #f5b800; color:#123f73; font-size:11.5px; margin:11px 0 6px; padding-bottom:3px; text-transform:uppercase; }
        .service-panel .section-title { margin-top:0; }
        .service-panel .sheet { margin-bottom:0; }
        .section-note { color:#64748b; font-size:8px; margin:-2px 0 5px; }
        .executive-box { background:#f4f8fc; border:1px solid #cbd8e6; margin-bottom:8px; padding:8px 10px; }
        .executive-box p { margin:0 0 4px; }
        .executive-box p:last-child { margin-bottom:0; }
        .highlight { color:#123f73; font-weight:bold; }
        .sheet { margin-bottom:8px; }
        .sheet thead { display:table-header-group; }
        .sheet th { background:#123f73; border:1px solid #b9c8d8; color:#fff; font-size:7px; padding:5px 4px; text-transform:uppercase; }
        .sheet th.ranking-banner { background:#e8f0f8; color:#123f73; font-size:8px; text-align:left; }
        .sheet td { border:1px solid #cbd5df; padding:4px; vertical-align:middle; }
        .sheet tbody tr:nth-child(even) td { background:#f7f9fc; }
        .sheet tbody tr { page-break-inside:avoid; }
        .sheet tfoot td { background:#eaf1f8; border:1px solid #b9c8d8; color:#123f73; font-weight:bold; padding:5px 4px; }
        .right { text-align:right; }
        .center { text-align:center; }
        .strong { font-weight:bold; }
        .muted { color:#64748b; }
        .nowrap { white-space:nowrap; }
        .positive { color:#23734f; font-weight:bold; }
        .negative { color:#a64343; font-weight:bold; }
        .neutral { color:#64748b; }
        .page-break { page-break-before:always; }
        .leaders { border-collapse:separate; border-spacing:5px 0; margin:0 -5px 7px; width:calc(100% + 10px); }
        .leaders td { background:#f4f8fc; border:1px solid #cbd8e6; border-left:4px solid #f5b800; padding:7px 9px; vertical-align:top; width:50%; }
        .leader-name { color:#123f73; font-size:10px; font-weight:bold; margin-top:2px; }
        .leader-detail { color:#526174; font-size:8px; margin-top:2px; }
        .method-note { background:#f8fafc; border-left:3px solid #94a3b8; color:#526174; font-size:7.5px; line-height:1.4; margin-top:8px; padding:7px 9px; }
        .footer { border-top:1px solid #d7e0e9; bottom:-11mm; color:#718096; font-size:7px; left:0; padding-top:4px; position:fixed; right:0; }
        .footer .page { float:right; }
        .footer .page:after { content:counter(page); }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/AGBClogo2.png');
        $logoData = is_file($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
        $formatCount = fn ($value) => \App\Support\BolivianNumber::format((int) $value);
        $formatWeight = fn ($value) => \App\Support\BolivianNumber::format((float) $value, 3);
        $change = function ($before, $after) {
            if ((float) $before === 0.0) {
                return ['label' => (float) $after === 0.0 ? '-' : 'Nuevo', 'class' => (float) $after === 0.0 ? 'neutral' : 'positive'];
            }

            $difference = (float) $after - (float) $before;
            $percentage = abs($difference / abs((float) $before) * 100);
            $sign = $difference > 0 ? '+' : ($difference < 0 ? '-' : '');

            return [
                'label' => $sign.number_format($percentage, 1, ',', '.').'%',
                'class' => $difference > 0 ? 'positive' : ($difference < 0 ? 'negative' : 'neutral'),
            ];
        };
    @endphp

    <div class="footer">Correos de Bolivia | Reporte ejecutivo de flujo de paquetería <span class="page">Página </span></div>

    <table class="header">
        <tr>
            <td style="width:45%">
                @if($logoData)<img class="logo" src="data:image/png;base64,{{ $logoData }}" alt="Correos de Bolivia">@endif
            </td>
            <td class="institution" style="width:55%">
                <strong>Correos de Bolivia</strong>
                Dirección de Operaciones<br>Informe para toma de decisiones
            </td>
        </tr>
    </table>

    <div class="title-block">
        <h1 class="title">Flujo de paquetería</h1>
        <div class="subtitle">Reporte ejecutivo de guías, transporte y paquetes EMS</div>
    </div>

    <table class="meta">
        <tr>
            <td><span class="label">Periodo analizado</span>{{ $periodLabel }}</td>
            <td><span class="label">Fecha de emisión</span>{{ now()->format('d/m/Y H:i') }}</td>
            <td><span class="label">Cobertura</span>{{ count($selectedMonths) }} {{ count($selectedMonths) === 1 ? 'mes seleccionado' : 'meses seleccionados' }} de {{ $anio }}</td>
        </tr>
        <tr>
            <td colspan="3"><span class="label">Departamentos de origen</span>{{ $departmentLabel }}</td>
        </tr>
    </table>

    <div class="scope-note"><strong>Alcance:</strong> resumen de guías y kilos recibidos de contratos, paquetes EMS y peso de CN-33 registrado en bitácoras durante el periodo elegido, con origen en {{ $departmentLabel }}.</div>

    <table class="kpis">
        <tr>
            <td><span class="label">Guías procesadas - suma mensual</span><span class="metric-value">{{ $formatCount($totals['guias_total']) }}</span><span class="metric-note">{{ $formatCount($totals['guias_contrato']) }} de contrato | {{ $formatCount($totals['guias_ems']) }} EMS</span></td>
            <td><span class="label">Peso total recibido</span><span class="metric-value">{{ $formatWeight($totals['peso_recibido']) }} kg</span><span class="metric-note">Contratos + EMS del periodo</span></td>
            <td><span class="label">Carga aérea CN-33</span><span class="metric-value">{{ $formatWeight($totals['aereo']) }} kg</span><span class="metric-note">BOA, BOA Cargo o Boliviana de Aviación</span></td>
            <td><span class="label">Carga terrestre CN-33</span><span class="metric-value">{{ $formatWeight($totals['terrestre']) }} kg</span><span class="metric-note">Las demás transportadoras</span></td>
            <td><span class="label">Paquetes EMS</span><span class="metric-value">{{ $formatCount($totals['paquetes_ems']) }}</span><span class="metric-note">{{ $formatWeight($totals['peso_ems']) }} kg registrados</span></td>
        </tr>
    </table>

    <h2 class="section-title">Resumen ejecutivo</h2>
    <div class="executive-box">
        @if($totals['guias_total'] > 0 || $totals['peso_recibido'] > 0 || $totals['paquetes_ems'] > 0 || $totals['aereo'] > 0 || $totals['terrestre'] > 0)
            <p>En <span class="highlight">{{ $periodLabel }}</span> con origen en <span class="highlight">{{ $departmentLabel }}</span> se procesaron <span class="highlight">{{ $formatCount($totals['guias_total']) }} guías</span> y se recibieron <span class="highlight">{{ $formatWeight($totals['peso_recibido']) }} kg en total</span> entre contratos y EMS.</p>
            <p>Contratos: <span class="highlight">{{ $formatCount($totals['guias_contrato']) }} guías y {{ $formatWeight($totals['peso_contrato']) }} kg</span>. EMS: <span class="highlight">{{ $formatCount($totals['guias_ems']) }} guías, {{ $formatCount($totals['paquetes_ems']) }} paquetes y {{ $formatWeight($totals['peso_ems']) }} kg</span>.</p>
            <p>Las bitácoras de CN-33 sumaron <span class="highlight">{{ $formatWeight($totals['aereo']) }} kg por vía aérea</span> y <span class="highlight">{{ $formatWeight($totals['terrestre']) }} kg por vía terrestre</span>, según el nombre de la transportadora registrada.</p>
        @else
            <p>No se encontraron movimientos para los meses seleccionados. Revisa el periodo y los datos registrados antes de emitir conclusiones.</p>
        @endif
    </div>

    <table class="service-details">
        <tr>
            <td class="service-panel">
                <h2 class="section-title">Servicio de contratos por mes</h2>
                <p class="section-note">Guías y kilos recibidos. Variación de guías frente al mes seleccionado anterior.</p>
                <table class="sheet">
                    <thead>
                        <tr><th style="width:24%">Mes</th><th class="right" style="width:22%">Guías contrato</th><th class="right" style="width:32%">Peso recibido (kg)</th><th class="center" style="width:22%">Var. guías</th></tr>
                    </thead>
                    <tbody>
                        @php $previousContractMonth = null; @endphp
                        @foreach($months as $month)
                            @php $contractChange = $previousContractMonth ? $change($previousContractMonth['guias_contrato'], $month['guias_contrato']) : null; @endphp
                            <tr>
                                <td class="strong">{{ $month['nombre'] }}</td>
                                <td class="right strong">{{ $formatCount($month['guias_contrato']) }}</td>
                                <td class="right">{{ $formatWeight($month['peso_contrato']) }}</td>
                                <td class="center {{ $contractChange['class'] ?? 'neutral' }}">{{ $contractChange['label'] ?? '-' }}</td>
                            </tr>
                            @php $previousContractMonth = $month; @endphp
                        @endforeach
                    </tbody>
                    @if(count($months) > 1)
                        <tfoot><tr><td>Suma mensual</td><td class="right">{{ $formatCount($totals['guias_contrato']) }}</td><td class="right">{{ $formatWeight($totals['peso_contrato']) }}</td><td class="center">-</td></tr></tfoot>
                    @endif
                </table>
            </td>
            <td class="service-gap"></td>
            <td class="service-panel">
                <h2 class="section-title">Servicio EMS por mes</h2>
                <p class="section-note">Guías, paquetes y kilos recibidos. Variación de guías frente al mes seleccionado anterior.</p>
                <table class="sheet">
                    <thead>
                        <tr><th style="width:22%">Mes</th><th class="right" style="width:16%">Guías EMS</th><th class="right" style="width:19%">Paquetes EMS</th><th class="right" style="width:27%">Peso recibido (kg)</th><th class="center" style="width:16%">Var. guías</th></tr>
                    </thead>
                    <tbody>
                        @php $previousEmsMonth = null; @endphp
                        @foreach($months as $month)
                            @php $emsChange = $previousEmsMonth ? $change($previousEmsMonth['guias_ems'], $month['guias_ems']) : null; @endphp
                            <tr>
                                <td class="strong">{{ $month['nombre'] }}</td>
                                <td class="right strong">{{ $formatCount($month['guias_ems']) }}</td>
                                <td class="right">{{ $formatCount($month['paquetes_ems']) }}</td>
                                <td class="right">{{ $formatWeight($month['peso_ems']) }}</td>
                                <td class="center {{ $emsChange['class'] ?? 'neutral' }}">{{ $emsChange['label'] ?? '-' }}</td>
                            </tr>
                            @php $previousEmsMonth = $month; @endphp
                        @endforeach
                    </tbody>
                    @if(count($months) > 1)
                        <tfoot><tr><td>Suma mensual</td><td class="right">{{ $formatCount($totals['guias_ems']) }}</td><td class="right">{{ $formatCount($totals['paquetes_ems']) }}</td><td class="right">{{ $formatWeight($totals['peso_ems']) }}</td><td class="center">-</td></tr></tfoot>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    <div class="consolidated-section">
        <h2 class="section-title">Consolidado mensual - contratos + EMS</h2>
        <p class="section-note">Suma de guías y kilos recibidos de ambos servicios. La variación compara el total de guías con el mes seleccionado anterior.</p>
        <table class="sheet">
            <thead>
                <tr>
                    <th style="width:22%">Mes</th>
                    <th class="right" style="width:26%">Total guías (contratos + EMS)</th>
                    <th class="right" style="width:32%">Peso total recibido (kg)</th>
                    <th class="center" style="width:20%">Var. total guías</th>
                </tr>
            </thead>
            <tbody>
                @php $previousConsolidatedMonth = null; @endphp
                @foreach($months as $month)
                    @php
                        $receivedWeight = $month['peso_contrato'] + $month['peso_ems'];
                        $consolidatedChange = $previousConsolidatedMonth ? $change($previousConsolidatedMonth['guias_total'], $month['guias_total']) : null;
                    @endphp
                    <tr>
                        <td class="strong">{{ $month['nombre'] }}</td>
                        <td class="right strong">{{ $formatCount($month['guias_total']) }}</td>
                        <td class="right strong">{{ $formatWeight($receivedWeight) }}</td>
                        <td class="center {{ $consolidatedChange['class'] ?? 'neutral' }}">{{ $consolidatedChange['label'] ?? '-' }}</td>
                    </tr>
                    @php $previousConsolidatedMonth = $month; @endphp
                @endforeach
            </tbody>
            @if(count($months) > 1)
                <tfoot><tr><td>Suma mensual</td><td class="right">{{ $formatCount($totals['guias_total']) }}</td><td class="right">{{ $formatWeight($totals['peso_recibido']) }}</td><td class="center">-</td></tr></tfoot>
            @endif
        </table>

        <div class="method-note">
            <strong>Cómo leer el detalle:</strong> las guías son códigos únicos por mes; un mismo código puede contarse en meses distintos. Contratos y EMS se filtran por la columna created_at de su tabla, y los conteos y pesos de esos paquetes excluyen el estado actual CANCELADO. El peso recibido suma sus kilos y los paquetes EMS suman el campo cantidad. Los kilos CN-33 son un cálculo aparte: usan bitacoras.created_at de la última bitácora por código y no filtran por estado individual del paquete. Cada variación compara las guías del servicio con el mes seleccionado anterior. En contratos también se excluyen la empresa "EMPRESA" y las empresas cuyo nombre contiene "prueba".
        </div>
    </div>

    <div class="page-break"></div>

    <table class="header">
        <tr>
            <td style="width:45%">
                @if($logoData)<img class="logo" src="data:image/png;base64,{{ $logoData }}" alt="Correos de Bolivia">@endif
            </td>
            <td class="institution" style="width:55%">
                <strong>Correos de Bolivia</strong>
                Dirección de Operaciones<br>Flujo de paquetería | {{ $periodLabel }}
            </td>
        </tr>
    </table>

    <div class="title-block">
        <h1 class="title">Transporte y empresas</h1>
        <div class="subtitle">Detalle de kilos CN-33 y movimiento de empresas contratadas</div>
    </div>

    <h2 class="section-title">Kilos de CN-33 registrados por mes - aéreo y terrestre</h2>
    <p class="section-note">Peso tomado de bitacoras.peso y agrupado por fecha de registro de la bitácora. Se clasifica como aéreo si transportadora es BOA, contiene BOA CARGO o BOLIVIANA DE AVIACIÓN; todas las demás se consideran terrestres. La variación compara el peso total con el mes seleccionado anterior.</p>
    <table class="sheet">
        <thead>
            <tr>
                <th style="width:14%">Mes</th>
                <th class="right" style="width:24%">Aéreo (kg)</th>
                <th class="right" style="width:25%">Terrestre (kg)</th>
                <th class="right" style="width:22%">Total aéreo + terrestre (kg)</th>
                <th class="center" style="width:15%">Var. total</th>
            </tr>
        </thead>
        <tbody>
            @php
                $previousDispatchWeight = null;
            @endphp
            @foreach($months as $month)
                @php
                    $dispatchTotal = $month['transporte']['aereo'] + $month['transporte']['terrestre'];
                    $dispatchChange = $previousDispatchWeight !== null ? $change($previousDispatchWeight, $dispatchTotal) : null;
                @endphp
                <tr>
                    <td class="strong">{{ $month['nombre'] }}</td>
                    <td class="right">{{ $formatWeight($month['transporte']['aereo']) }}</td>
                    <td class="right">{{ $formatWeight($month['transporte']['terrestre']) }}</td>
                    <td class="right strong">{{ $formatWeight($dispatchTotal) }}</td>
                    <td class="center {{ $dispatchChange['class'] ?? 'neutral' }}">{{ $dispatchChange['label'] ?? '-' }}</td>
                </tr>
                @php
                    $previousDispatchWeight = $dispatchTotal;
                @endphp
            @endforeach
        </tbody>
        @if(count($months) > 1)
            <tfoot><tr><td>Suma del periodo</td><td class="right">{{ $formatWeight($totals['aereo']) }}</td><td class="right">{{ $formatWeight($totals['terrestre']) }}</td><td class="right">{{ $formatWeight($totals['aereo'] + $totals['terrestre']) }}</td><td class="center">-</td></tr></tfoot>
        @endif
    </table>

    <h2 class="section-title">Empresas con mayor movimiento de contratos</h2>
    <p class="section-note">Líderes por guías y kilos. Se incluyen las {{ \App\Support\BolivianNumber::format(count($companyRows)) }} empresas identificadas, excepto la empresa "EMPRESA" y las empresas de prueba; el ranking está ordenado por guías y muestra el peso acumulado en {{ $periodLabel }}.</p>
    <table class="leaders">
        <tr>
            <td>
                <span class="label">Más guías</span>
                <div class="leader-name">{{ $topByGuides['empresa'] ?? 'Sin registros identificados' }}</div>
                @if($topByGuides)<div class="leader-detail">{{ $formatCount($topByGuides['guias_total']) }} guías | {{ $formatWeight($topByGuides['peso_total']) }} kg</div>@endif
            </td>
            <td>
                <span class="label">Más kilos</span>
                <div class="leader-name">{{ $topByWeight['empresa'] ?? 'Sin registros identificados' }}</div>
                @if($topByWeight)<div class="leader-detail">{{ $formatWeight($topByWeight['peso_total']) }} kg | {{ $formatCount($topByWeight['guias_total']) }} guías</div>@endif
            </td>
        </tr>
    </table>

    <table class="sheet">
        <thead>
            <tr><th class="ranking-banner" colspan="4">Empresas contratadas - {{ $periodLabel }}</th></tr>
            <tr>
                <th class="center" style="width:8%">Pos.</th>
                <th style="width:48%">Empresa contratada</th>
                <th class="right" style="width:22%">Guías</th>
                <th class="right" style="width:22%">Peso (kg)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($companyRows as $index => $company)
                <tr>
                    <td class="center muted">{{ $index + 1 }}</td>
                    <td class="strong">{{ $company['empresa'] }}</td>
                    <td class="right">{{ $formatCount($company['guias_total']) }}</td>
                    <td class="right">{{ $formatWeight($company['peso_total']) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="center muted">No hay paquetes de contrato asociados a empresas para este periodo.</td></tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
