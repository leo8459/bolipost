<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Reporte ejecutivo de ingresos</title>
    <style>
        @page { margin: 17mm 13mm 17mm; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 8.5px; line-height: 1.35; color: #172033; }
        .header, .meta, .metrics, .sheet, .signatures { width: 100%; border-collapse: collapse; }
        .header { margin-bottom: 12px; }
        .header td { vertical-align: middle; }
        .logo { width: 142px; max-height: 50px; }
        .institution { text-align: right; color: #31547d; font-size: 8px; text-transform: uppercase; letter-spacing: .35px; }
        .institution strong { display: block; color: #153f72; font-size: 10.5px; }
        .title-block { padding: 12px 15px; border-left: 5px solid #f5b800; background: #123f73; color: #fff; }
        .title { margin: 0 0 3px; font-size: 18px; text-transform: uppercase; }
        .subtitle { color: #dce9f6; font-size: 9.5px; }
        .meta { margin: 9px 0; }
        .meta td { width: 33.33%; padding: 6px 7px; border: 1px solid #d7e0e9; background: #f7f9fc; }
        .label { display: block; margin-bottom: 2px; color: #64748b; font-size: 7px; font-weight: bold; text-transform: uppercase; }
        .scope-note { margin: 0 0 10px; padding: 8px 10px; border: 1px solid #efd47b; border-left: 4px solid #f5b800; background: #fff9e6; color: #5f5230; }
        .audit-warning { margin: 0 0 10px; padding: 8px 10px; border: 1px solid #e3a7a7; border-left: 4px solid #bb3333; background: #fff1f1; color: #7b2525; }
        .metrics { margin-bottom: 10px; border-collapse: separate; border-spacing: 4px 0; }
        .metrics td { width: 20%; padding: 8px 6px; border: 1px solid #d7e0e9; border-top: 3px solid #f5b800; }
        .metric-value { display: block; margin-top: 2px; color: #123f73; font-size: 13px; font-weight: bold; }
        .section-title { margin: 12px 0 6px; padding-bottom: 3px; border-bottom: 2px solid #f5b800; color: #123f73; font-size: 11.5px; text-transform: uppercase; }
        .executive-box { padding: 9px 11px; border: 1px solid #cbd8e6; background: #f4f8fc; }
        .executive-box p { margin: 0 0 5px; }
        .executive-box p:last-child { margin-bottom: 0; }
        .highlight { color: #123f73; font-weight: bold; }
        .sheet { margin-bottom: 10px; }
        .sheet thead { display: table-header-group; }
        .sheet th { padding: 5px 4px; border: 1px solid #b9c8d8; background: #123f73; color: #fff; font-size: 7px; text-transform: uppercase; }
        .sheet td { padding: 4px; border: 1px solid #cbd5df; vertical-align: top; }
        .sheet tbody tr:nth-child(even) td { background: #f7f9fc; }
        .right { text-align: right; }
        .center { text-align: center; }
        .strong { font-weight: bold; }
        .money { white-space: nowrap; color: #173f6d; font-weight: bold; }
        .muted { color: #64748b; }
        .nowrap { white-space: nowrap; }
        .page-break { page-break-before: always; }
        .department-page { page-break-before: always; }
        .department-head { width: 100%; margin-bottom: 8px; border-collapse: collapse; }
        .department-head td { padding: 9px 11px; border: 1px solid #cbd8e6; background: #f4f8fc; }
        .department-name { color: #123f73; font-size: 15px; font-weight: bold; }
        .department-subtitle { margin-top: 3px; color: #64748b; font-size: 8px; }
        .sheet tr { page-break-inside: avoid; }
        .signatures { margin: 20px 0 8px; border-collapse: separate; border-spacing: 25px 0; page-break-inside: avoid; }
        .signatures td { width: 50%; padding-top: 7px; border-top: 1px solid #64748b; text-align: center; color: #475569; }
        .method-note { margin-top: 10px; padding: 7px 9px; border-left: 3px solid #94a3b8; background: #f8fafc; color: #526174; font-size: 7.5px; }
        .footer { position: fixed; right: 0; bottom: -11mm; left: 0; padding-top: 4px; border-top: 1px solid #d7e0e9; color: #718096; font-size: 7px; }
        .footer .page { float: right; }
        .footer .page:after { content: counter(page); }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('images/AGBClogo2.png');
        $logoData = is_file($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;
        $totalAmount = (float) ($summary['totalMontoVendido'] ?? $summary['totalMonto'] ?? 0);
        $totalSales = (float) ($summary['cantidadVentas'] ?? 0);
        $totalCollectedReportAmount = (float) ($totalReportAmountIncludingReceivables ?? $totalReportIncome ?? $totalAmount);
        $incomeWithoutContractsEca = (float) ($summary['totalSinContratosEca'] ?? $summary['totalMontoVendido'] ?? $summary['totalMonto'] ?? 0);
        $incomeContractsEca = $totalCollectedReportAmount - $incomeWithoutContractsEca;
    @endphp

    <div class="footer">
        Documento de uso interno - Dirección Financiera
        <span class="page">Página </span>
    </div>

    <table class="header">
        <tr>
            <td>
                @if($logoData)
                    <img class="logo" src="data:image/png;base64,{{ $logoData }}" alt="Correos de Bolivia">
                @endif
            </td>
            <td class="institution">
                <strong>Correos de Bolivia</strong>
                Dirección Financiera<br>Informe para toma de decisiones
            </td>
        </tr>
    </table>

    <div class="title-block">
        <h1 class="title">Reporte ejecutivo de ingresos</h1>
        <div class="subtitle">Ingresos por departamento, cajero y servicio</div>
    </div>

    <table class="meta">
        <tr>
            <td><span class="label">Periodo analizado</span>{{ $periodLabel }}</td>
            <td><span class="label">Fecha de emisión</span>{{ $generatedAt->format('d/m/Y H:i') }}</td>
            <td><span class="label">Departamento</span>{{ $selectedDepartment !== '' ? $selectedDepartment : 'Todos los departamentos' }}</td>
        </tr>
    </table>

    <div class="scope-note">
        <strong>Alcance:</strong> los ingresos recaudados reúnen las ventas de ventanilla y la facturación de Contratos y ECA asignada a cada usuario. @if(!$cashierFlowCollectionsOmittedByDepartment)Los cobros aceptados se atribuyen a quien facturó; el detalle conserva la fecha de factura y el nombre del facturador.@else Los cobros aceptados se omiten porque hay un filtro de departamento.@endif El promedio diario divide los ingresos recaudados entre los días seleccionados, excluyendo domingos.
    </div>
    @if(($cashierFlowCancellationLookupErrors ?? collect())->isNotEmpty())
        <div class="audit-warning">
            <strong>Verificación incompleta:</strong> no se pudo consultar el detalle de {{ $cashierFlowCancellationLookupErrors->count() }} servicio(s)/mes(es) en la API. El desglose por medio de pago puede quedar incompleto y algunos totales podrían conservar facturas anuladas.
        </div>
    @endif
    @if(($cashierFlowReceivableDepartmentErrors ?? collect())->isNotEmpty())
        <div class="audit-warning">
            <strong>Detalle de facturas incompleto:</strong> el saldo pendiente se distribuye según el resumen por usuario y el departamento registrado de quien facturó; los casos sin departamento quedan en “Sin regional asignada”.
        </div>
    @endif

    <table class="metrics">
        <tr>
            <td><span class="label">Ingresos recaudados (sin Contratos ni ECA)</span><span class="metric-value">Bs {{ \App\Support\BolivianNumber::format($incomeWithoutContractsEca, 2) }}</span></td>
            <td><span class="label">Ingresos Contratos y ECA</span><span class="metric-value">Bs {{ \App\Support\BolivianNumber::format($incomeContractsEca, 2) }}</span></td>
            <td><span class="label">Total recaudado</span><span class="metric-value">Bs {{ \App\Support\BolivianNumber::format($totalCollectedReportAmount, 2) }}</span></td>
            <td><span class="label">Promedio diario</span><span class="metric-value">Bs {{ \App\Support\BolivianNumber::format((float) ($averageDailyIncome ?? 0), 2) }}</span></td>
            <td><span class="label">Cajeros</span><span class="metric-value">{{ \App\Support\BolivianNumber::format($cashierRows->count()) }}</span></td>
        </tr>
    </table>

    <h2 class="section-title">Resumen ejecutivo</h2>
    <div class="executive-box">
        <p><strong>Mejor cajero (quien recaudó más):</strong> @if($topCashier) {{ $topCashier['usuarioNombre'] }} — Bs {{ \App\Support\BolivianNumber::format((float) ($topCashier['totalIngresosIncluyendoPorCobrar'] ?? $topCashier['totalIngresos'] ?? $topCashier['totalMonto']), 2) }} @else Sin datos @endif</p>
        <p><strong>Mejor servicio:</strong> @if($topService) {{ $topService['servicio'] }} — Bs {{ \App\Support\BolivianNumber::format((float) ($topService['totalMontoVendido'] ?? $topService['totalMonto'] ?? 0), 2) }} @else Sin datos @endif</p>
        <p><strong>Cantidad de ventas:</strong> {{ \App\Support\BolivianNumber::format($totalSales) }}</p>
        <p><strong>Total de anulaciones:</strong> Bs {{ \App\Support\BolivianNumber::format((float) ($summary['totalMontoAnulado'] ?? 0), 2) }}</p>
        <p><strong>QR ANULADOS:</strong> Bs {{ \App\Support\BolivianNumber::format((float) ($summary['totalMontoNoIncluidoEnTotalVendido'] ?? 0), 2) }}</p>
    </div>

    <h2 class="section-title">Resumen por departamento</h2>
<table class="sheet">
    <thead>
        <tr>
            <th style="width: 23%">Departamento</th>
            <th style="width: 10%" class="right">Cajeros</th>
            <th style="width: 12%" class="right">Facturados</th>
            <th style="width: 12%" class="right">Paquetes</th>
            <th style="width: 17%" class="right">Promedio de ingresos recaudados/día</th>
            <th style="width: 26%" class="right">Ingresos recaudados</th>
        </tr>
    </thead>
    <tbody>
        @forelse($cashierDepartmentGroups as $departmentGroup)
            <tr>
                <td class="strong">{{ $departmentGroup['departamento'] }}</td>
                <td class="right">{{ \App\Support\BolivianNumber::format($departmentGroup['cantidadCajeros']) }}</td>
                <td class="right">{{ \App\Support\BolivianNumber::format($departmentGroup['cantidadVentas']) }}</td>
                <td class="right">{{ \App\Support\BolivianNumber::format($departmentGroup['totalCantidad'], 2) }}</td>
                <td class="right money">Bs {{ \App\Support\BolivianNumber::format($departmentGroup['promedioGeneradoDiario'], 2) }}</td>
                <td class="right money">Bs {{ \App\Support\BolivianNumber::format($departmentGroup['totalIngresos'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="center muted">No hay información por cajero para los filtros seleccionados.</td></tr>
        @endforelse
        @if($cashierDepartmentGroups->isNotEmpty())
            <tr>
                <td class="strong">TOTAL GENERAL</td>
                <td class="right strong">{{ \App\Support\BolivianNumber::format((float) $cashierDepartmentGroups->sum('cantidadCajeros')) }}</td>
                <td class="right strong">{{ \App\Support\BolivianNumber::format((float) ($summary['cantidadVentas'] ?? $cashierDepartmentGroups->sum('cantidadVentas'))) }}</td>
                <td class="right strong">{{ \App\Support\BolivianNumber::format((float) $cashierDepartmentGroups->sum('totalCantidad'), 2) }}</td>
                <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) $cashierDepartmentGroups->sum('promedioGeneradoDiario'), 2) }}</td>
                <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) $cashierDepartmentGroups->sum('totalIngresos'), 2) }}</td>
            </tr>
        @endif
    </tbody>
</table>
<p class="muted">Los subtotales por departamento y el total general usan ventas únicas. Las filas de cajeros muestran facturados por servicio y pueden solaparse si una venta incluye varios servicios. Los cajeros asignados a varias regionales aparecen en una sección propia para evitar duplicar sus ingresos.</p>
@if(!empty($cashierFlowDepartmentSalesCountWarning))
    <p class="muted">{{ $cashierFlowDepartmentSalesCountWarning }}</p>
@endif

@foreach($cashierDepartmentGroups as $departmentGroup)
    <div class="department-page">
        <table class="department-head">
            <tr>
                <td>
                    <div class="label">Detalle por departamento</div>
                    <div class="department-name">{{ $departmentGroup['departamento'] }}</div>
                    <div class="department-subtitle">
                        {{ \App\Support\BolivianNumber::format($departmentGroup['cantidadCajeros']) }} cajeros | {{ $periodLabel }}
                        | Ingresos recaudados: Bs {{ \App\Support\BolivianNumber::format($departmentGroup['totalIngresos'], 2) }}
                    </div>
                </td>
            </tr>
        </table>
        <table class="sheet">
            <thead>
                <tr>
                    <th style="width: 4%" class="center">Pos.</th>
                    <th style="width: 25%">Cajero</th>
                    <th style="width: 10%" class="right">Facturados</th>
                    <th style="width: 11%" class="right">Paquetes</th>
                    <th style="width: 14%" class="right">Promedio de ingresos recaudados/día</th>
                    <th style="width: 12%" class="right">QR</th>
                    <th style="width: 12%" class="right">Efectivo</th>
                    <th style="width: 12%" class="right">Ingresos recaudados</th>
                </tr>
            </thead>
            <tbody>
                @foreach($departmentGroup['cajeros'] as $cashier)
                    <tr>
                        <td class="center">{{ $loop->iteration }}</td>
                        <td><span class="strong">{{ $cashier['usuarioNombre'] }}</span>@if($cashier['usuarioCarnet'] !== '')<br><span class="muted">CI: {{ $cashier['usuarioCarnet'] }}</span>@endif</td>
                        <td class="right">{{ \App\Support\BolivianNumber::format((float) ($cashier['cantidadVentas'] ?? 0)) }}</td>
                        <td class="right">{{ \App\Support\BolivianNumber::format((float) ($cashier['totalCantidad'] ?? 0), 2) }}</td>
                        <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($cashier['promedioGeneradoDiario'] ?? 0), 2) }}</td>
                        <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($cashier['paymentMethods']['qr']['totalMonto'] ?? 0), 2) }}</td>
                        <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($cashier['paymentMethods']['efectivo']['totalMonto'] ?? 0), 2) }}</td>
                        <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($cashier['totalIngresos'] ?? $cashier['totalMonto']), 2) }}</td>
                    </tr>
                @endforeach
                <tr>
                    <td colspan="2" class="strong">TOTAL {{ strtoupper($departmentGroup['departamento']) }}</td>
                    <td class="right strong">{{ \App\Support\BolivianNumber::format($departmentGroup['cantidadVentas']) }}</td>
                    <td class="right strong">{{ \App\Support\BolivianNumber::format($departmentGroup['totalCantidad'], 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format($departmentGroup['promedioGeneradoDiario'], 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format(collect($departmentGroup['cajeros'])->sum(fn ($cashier) => (float) data_get($cashier, 'paymentMethods.qr.totalMonto', 0)), 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format(collect($departmentGroup['cajeros'])->sum(fn ($cashier) => (float) data_get($cashier, 'paymentMethods.efectivo.totalMonto', 0)), 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format($departmentGroup['totalIngresos'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
@endforeach
<h2 class="section-title">Ingresos por servicio</h2>
<p class="muted">VENTANILLAS muestra ventas únicas; los conteos por servicio pueden solaparse cuando una venta incluye varios servicios. Cobrado + Cobrado QR = Total recaudado; las anulaciones y los saldos por cobrar no se incluyen.</p>
    <table class="sheet">
        <thead>
            <tr>
                <th style="width: 5%">Pos.</th>
                <th style="width: 27%">Grupo de servicio</th>
                <th style="width: 11%" class="right">Operaciones por servicio</th>
                <th style="width: 12%" class="right">Cantidad de paquetes</th>
                <th style="width: 12%" class="right">Cobrado</th>
                <th style="width: 11%" class="right">Cobrado QR</th>
                <th style="width: 13%" class="right">Total recaudado</th>
                <th style="width: 9%" class="right">Por cobrar</th>
            </tr>
        </thead>
        <tbody>
            @forelse($serviceGroups as $group)
                @php($groupNumber = $loop->iteration)
                <tr>
                    <td class="center strong">{{ $groupNumber }}</td>
                    <td class="strong">{{ $group['servicio'] }}</td>
                    <td class="right">{{ \App\Support\BolivianNumber::format((float) $group['cantidadVentas']) }}</td>
                    <td class="right">{{ \App\Support\BolivianNumber::format((float) $group['totalCantidad'], 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoCobrado'] ?? 0), 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoCobradoQr'] ?? 0), 2) }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoRecaudado'] ?? 0), 2) }}</td>
                    <td class="right money">@if(($group['servicio'] ?? '') === 'Contratos')Bs {{ \App\Support\BolivianNumber::format((float) ($group['totalMontoPorCobrar'] ?? 0), 2) }}@else—@endif</td>
                </tr>
                @foreach($group['_children'] ?? [] as $child)
                    @php($childNumber = $loop->iteration)
                    @if(($group['servicio'] ?? '') === 'VENTANILLAS')
                        <tr>
                            <td class="center muted">{{ $groupNumber }}.{{ $childNumber }}</td>
                            <td style="padding-left: 20px" class="strong">{{ $child['servicio'] }}</td>
                            <td class="right">{{ \App\Support\BolivianNumber::format((float) ($child['cantidadVentas'] ?? 0)) }}</td>
                            <td class="right">{{ \App\Support\BolivianNumber::format((float) ($child['totalCantidad'] ?? 0), 2) }}</td>
                            <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoCobrado'] ?? 0), 2) }}</td>
                            <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoCobradoQr'] ?? 0), 2) }}</td>
                            <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoRecaudado'] ?? 0), 2) }}</td>
                            <td class="right">—</td>
                        </tr>
                        @foreach($child['_children'] ?? [] as $service)
                            @php($serviceNumber = $loop->iteration)
                            <tr>
                                <td class="center muted">{{ $groupNumber }}.{{ $childNumber }}.{{ $serviceNumber }}</td>
                                <td style="padding-left: 38px">{{ $service['servicio'] ?? '-' }}</td>
                                <td class="right">{{ \App\Support\BolivianNumber::format((float) ($service['cantidadVentas'] ?? 0)) }}</td>
                                <td class="right">{{ \App\Support\BolivianNumber::format((float) ($service['totalCantidad'] ?? 0), 2) }}</td>
                                <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($service['totalMontoCobrado'] ?? 0), 2) }}</td>
                                <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($service['totalMontoCobradoQr'] ?? 0), 2) }}</td>
                                <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($service['totalMontoRecaudado'] ?? 0), 2) }}</td>
                                <td class="right">—</td>
                            </tr>
                        @endforeach
                    @else
                        <tr>
                            <td class="center muted">{{ $groupNumber }}.{{ $childNumber }}</td>
                            <td style="padding-left: 20px">{{ $child['servicio'] ?? '-' }}</td>
                            <td class="right">{{ \App\Support\BolivianNumber::format((float) ($child['cantidadVentas'] ?? 0)) }}</td>
                            <td class="right">{{ \App\Support\BolivianNumber::format((float) ($child['totalCantidad'] ?? 0), 2) }}</td>
                            <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoCobrado'] ?? 0), 2) }}</td>
                            <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoCobradoQr'] ?? 0), 2) }}</td>
                            <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoRecaudado'] ?? 0), 2) }}</td>
                            <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) ($child['totalMontoPorCobrar'] ?? $child['_montoPendiente'] ?? 0), 2) }}</td>
                        </tr>
                    @endif
                @endforeach
            @empty
                <tr><td colspan="8" class="center muted">Sin información por servicio.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="signatures">
        <tr>
            <td>Elaborado por<br><strong>Unidad responsable</strong></td>
            <td>Revisado por<br><strong>Director Financiero</strong></td>
        </tr>
    </table>

    <div class="method-note">
        <strong>Nota metodológica:</strong> los ingresos totales reúnen las ventas de ventanilla y los cobros aceptados de Contratos y ECA Internacional; las cuentas por cobrar pendientes se excluyen. Los importes por QR y efectivo muestran los montos identificados para cada cajero. El promedio diario divide los ingresos entre los días del periodo seleccionado, excluyendo domingos. El reporte debe contrastarse con los respaldos transaccionales antes del cierre contable definitivo.
    </div>

    <h2 class="section-title page-break">Facturas canceladas</h2>
    <p class="muted">Las facturas con estado fiscal anulado o estado de pago anulado/cancelado se muestran aquí y se excluyen de los ingresos por servicio, por cajero y del promedio diario.</p>
    <table class="sheet">
        <thead>
            <tr>
                <th style="width: 17%">Servicio</th>
                <th style="width: 10%">Fecha</th>
                <th style="width: 15%">Venta / detalle</th>
                <th style="width: 17%">Facturó</th>
                <th style="width: 10%">Medio de pago</th>
                <th style="width: 12%">Estado fiscal</th>
                <th style="width: 10%">Estado pago</th>
                <th style="width: 13%" class="right">Importe excluido</th>
            </tr>
        </thead>
        <tbody>
            @forelse(($cashierFlowCancelledInvoices ?? collect()) as $invoice)
                <tr>
                    <td class="strong">{{ $invoice['servicio'] }}</td>
                    <td>{{ $invoice['fecha'] !== '' ? $invoice['fecha'] : '-' }}</td>
                    <td>{{ $invoice['venta'] }}<br><span class="muted">Detalle: {{ $invoice['detalle'] }}</span></td>
                    <td>{{ $invoice['facturadoPor'] }}</td>
                    <td>{{ $invoice['medioPago'] !== '' ? $invoice['medioPago'] : '-' }}</td>
                    <td class="strong">{{ $invoice['estadoFiscal'] !== '' ? $invoice['estadoFiscal'] : '-' }}</td>
                    <td>{{ $invoice['estadoPago'] !== '' ? $invoice['estadoPago'] : '-' }}</td>
                    <td class="right money">Bs {{ \App\Support\BolivianNumber::format((float) $invoice['monto'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="center muted">No se encontraron facturas anuladas en los detalles consultados.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
