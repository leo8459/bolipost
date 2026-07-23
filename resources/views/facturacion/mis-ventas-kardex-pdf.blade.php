<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kardex Diario de Rendicion</title>
    <style>
        @page {
            margin: 16px 14px 18px 14px;
        }

        body {
            font-family: Verdana, DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        .no-border td {
            border: none;
        }

        .header-banner {
            width: 100%;
            height: auto;
            display: block;
        }

        .meta td {
            border: 1px solid #333;
            padding: 6px 8px;
            font-size: 10px;
        }

        .meta .field {
            width: 18%;
            font-weight: 700;
        }

        .meta .value {
            width: 32%;
        }

        .section-title {
            margin: 12px 0 6px;
            padding: 5px 8px;
            border: 1px solid #333;
            background: #f4f6f9;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .grid th,
        .grid td {
            border: 1px solid #333;
            padding: 4px 5px;
        }

        .grid th {
            text-align: center;
            font-weight: 700;
            font-size: 10px;
        }

        .grid td {
            font-size: 9.4px;
            height: 20px;
        }

        .center {
            text-align: center;
        }

        .right {
            text-align: right;
        }

        .totals td {
            border: 1px solid #333;
            padding: 5px 8px;
            font-size: 10px;
            font-weight: 700;
        }

        .observaciones td {
            border: 1px solid #333;
            padding: 8px;
            vertical-align: top;
        }

        .obs-label {
            width: 27%;
            font-weight: 700;
        }

        .obs-lines {
            padding: 0 !important;
        }

        .obs-grid {
            width: 100%;
            height: 90px;
            border-collapse: collapse;
        }

        .obs-grid td {
            border: 0;
            border-right: 1px solid #888;
            height: 90px;
            padding: 0 8px;
            font-size: 9px;
            vertical-align: top;
        }

        .obs-grid td:last-child {
            border-right: 0;
        }

        .page-break {
            page-break-before: always;
        }

        .cashier-meta td {
            border: 1px solid #333;
            padding: 5px 7px;
            font-size: 9.5px;
        }

        .cashier-name {
            font-weight: 700;
            font-size: 11px;
        }

        .cashier-muted {
            color: #444;
            font-size: 9px;
        }

        .summary-grid td {
            border: 1px solid #333;
            padding: 6px 8px;
            font-size: 10px;
        }

        .summary-label {
            font-weight: 700;
            text-transform: uppercase;
            background: #f4f6f9;
        }

        .report-footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            font-size: 9px;
            color: #444;
        }

        .report-footer td {
            border-top: 1px solid #cbd5e1;
            padding-top: 10px;
        }
    </style>
</head>
<body>
@php
    $scope = $scope ?? 'own';
    $headerImagePath = public_path('images/encabezado_contratos.jpeg');
    $headerImage = file_exists($headerImagePath) ? 'data:image/jpeg;base64,' . base64_encode(file_get_contents($headerImagePath)) : null;
    $sucursal = $user->sucursal;
    $oficinaPostal = trim((string) ($sucursal->nombre ?? $sucursal->descripcion ?? $sucursal->municipio ?? ''));
    $isAdmisionesEms = collect($carts)->contains(function ($cart) {
        $rawItems = data_get($cart, 'items', []);
        $cartItems = $rawItems instanceof \Illuminate\Support\Collection
            ? $rawItems
            : (is_array($rawItems) ? collect($rawItems) : collect());
        return $cartItems->contains(function ($item) {
            $titulo = strtoupper(trim((string) data_get($item, 'titulo', '')));
            $servicio = strtoupper(trim((string) data_get($item, 'nombre_servicio', '')));

            return str_contains($titulo, 'ADMISION EMS') || str_contains($servicio, 'EMS');
        });
    });
    $ventanilla = $isAdmisionesEms
        ? 'Admisiones'
        : ($sucursal ? ('Punto ' . trim((string) ($sucursal->puntoVenta ?? ''))) : '');
    $fechaRecaudacion = $filters['from'] && $filters['to']
        ? ($filters['from'] === $filters['to'] ? $filters['from'] : ($filters['from'] . ' al ' . $filters['to']))
        : ($filters['from'] ?: ($filters['to'] ?: $generatedAt->format('Y-m-d')));
    $observacionFiltros = collect([
        $filters['q'] !== '' ? 'Busqueda: ' . $filters['q'] : null,
        $filters['estado'] !== 'all' ? 'Estado: ' . strtoupper($filters['estado']) : null,
        $filters['estado_emision'] !== 'all' ? 'Emision: ' . $filters['estado_emision'] : null,
    ])->filter()->implode(' | ');
    $sectionConfigs = [
        'factura_electronica' => [
            'title' => 'Facturacion electronica',
            'total_label' => 'TOTAL FACTURACION ELECTRONICA EN CAJA',
        ],
        'factura_anulada' => [
            'title' => 'Ventas anuladas',
            'total_label' => 'TOTAL VENTAS ANULADAS (NO SUMAN A CAJA)',
        ],
        'qr_facturado' => [
            'title' => 'Pagos QR facturados',
            'total_label' => 'TOTAL QR FACTURADO REFERENCIAL NO SUMADO A CAJA',
        ],
        'qr_pagado_pendiente_factura' => [
            'title' => 'Pagos QR confirmados pendientes de factura',
            'total_label' => 'TOTAL QR PAGADO PENDIENTE DE FACTURA',
        ],
        'qr_pendiente' => [
            'title' => 'Pagos QR pendientes',
            'total_label' => 'TOTAL QR PENDIENTE',
        ],
        'qr_cancelado' => [
            'title' => 'Pagos QR no concretados',
            'total_label' => 'TOTAL QR CANCELADO / NO PAGADO',
        ],
        'oficial' => [
            'title' => 'Envios oficiales',
            'total_label' => 'TOTAL ENVIOS OFICIALES',
        ],
    ];
    $rowsByChannel = collect($rows)->groupBy(fn ($row) => (string) data_get($row, 'section_key', strtolower((string) data_get($row, 'canal_emision', 'factura_electronica'))));
    $paidSectionKeys = ['factura_electronica', 'qr_facturado', 'qr_pagado_pendiente_factura', 'oficial'];
    $annulledSectionKeys = ['factura_anulada'];
    $cashSectionKeys = ['factura_electronica', 'oficial'];
    $unpaidSectionKeys = ['qr_pendiente', 'qr_cancelado'];
    $hasPaidRows = collect($paidSectionKeys)->contains(fn ($key) => $rowsByChannel->get($key, collect())->isNotEmpty());
    $hasAnnulledRows = collect($annulledSectionKeys)->contains(fn ($key) => $rowsByChannel->get($key, collect())->isNotEmpty());
    $hasUnpaidRows = collect($unpaidSectionKeys)->contains(fn ($key) => $rowsByChannel->get($key, collect())->isNotEmpty());
    $paidRows = collect($rows)->filter(fn ($row) => in_array((string) data_get($row, 'section_key', ''), $paidSectionKeys, true))->values();
    $cashRows = collect($rows)->filter(fn ($row) => in_array((string) data_get($row, 'section_key', ''), $cashSectionKeys, true))->values();
    $annulledRows = collect($rows)->filter(fn ($row) => in_array((string) data_get($row, 'section_key', ''), $annulledSectionKeys, true))->values();
    $unpaidRows = collect($rows)->filter(fn ($row) => in_array((string) data_get($row, 'section_key', ''), $unpaidSectionKeys, true))->values();
    $effectiveDetailRows = collect($rows)
        ->filter(fn ($row) => strtolower(trim((string) data_get($row, 'metodo_pago', 'efectivo'))) !== 'qr')
        ->values();
    $qrDetailRows = collect($rows)
        ->filter(fn ($row) => strtolower(trim((string) data_get($row, 'metodo_pago', 'efectivo'))) === 'qr')
        ->values();
    $preSummary = [
        'total_ventas' => collect($rows)->count(),
        'total_emitido' => round((float) collect($rows)->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
        'efectivo_cantidad' => $effectiveDetailRows->count(),
        'efectivo_total' => round((float) $effectiveDetailRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
        'qr_cantidad' => $qrDetailRows->count(),
        'qr_total' => round((float) $qrDetailRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
    ];
    $branchGroups = collect($rows)
        ->groupBy(fn ($row) => trim((string) data_get($row, 'origen_usuario_id', data_get($row, 'origen_usuario_email', 'sin-usuario'))))
        ->map(function ($groupRows) {
            $groupRows = collect($groupRows)
                ->sortBy(fn ($row) => (int) data_get($row, 'fecha_sort', 0))
                ->values();
            $first = $groupRows->first();

            return [
                'usuario_id' => trim((string) data_get($first, 'origen_usuario_id', '')),
                'nombre' => trim((string) data_get($first, 'origen_usuario_nombre', 'Sin usuario')),
                'email' => trim((string) data_get($first, 'origen_usuario_email', '')),
                'rows' => $groupRows,
                'ventas' => $groupRows->count(),
                'cobradas' => $groupRows->filter(fn ($row) => (bool) data_get($row, 'cobrada', false))->count(),
                'pendientes' => $groupRows->filter(fn ($row) => ! (bool) data_get($row, 'cobrada', false))->count(),
                'total' => round((float) $groupRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
                'total_caja' => round((float) $groupRows
                    ->filter(fn ($row) => (bool) data_get($row, 'contabiliza_en_caja', false))
                    ->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
                'total_qr' => round((float) $groupRows
                    ->filter(fn ($row) => strtolower(trim((string) data_get($row, 'metodo_pago', 'efectivo'))) === 'qr')
                    ->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2),
            ];
        })
        ->sortByDesc('total')
        ->values();
@endphp

@if($headerImage)
    <div style="margin-bottom: 8px;">
        <img src="{{ $headerImage }}" class="header-banner" alt="Encabezado Contratos">
    </div>
@endif

<table class="meta" style="margin-bottom: 10px;">
    <tr>
        <td class="field">Oficina Postal:</td>
        <td class="value">{{ $oficinaPostal !== '' ? $oficinaPostal : '-' }}</td>
        <td class="field">{{ $scope === 'branch' ? 'Encargado regional:' : 'Nombre Responsable:' }}</td>
        <td class="value">{{ $user->name }}</td>
    </tr>
    <tr>
        <td class="field">Sistema:</td>
        <td class="value">SIOP</td>
        <td class="field">Fecha de recaudacion:</td>
        <td class="value">{{ $fechaRecaudacion }}</td>
    </tr>
</table>

<table class="summary-grid" style="margin-bottom: 10px;">
    <tr>
        <td style="width: 25%;"><strong>Total ventas: {{ number_format((int) $preSummary['total_ventas']) }}</strong></td>
        <td style="width: 25%;"><strong>Total emitido: Bs {{ number_format((float) $preSummary['total_emitido'], 2) }}</strong></td>
        <td style="width: 25%;"><strong>Efectivo: {{ number_format((int) $preSummary['efectivo_cantidad']) }} &nbsp; Bs {{ number_format((float) $preSummary['efectivo_total'], 2) }}</strong></td>
        <td style="width: 25%;"><strong>QR: {{ number_format((int) $preSummary['qr_cantidad']) }} &nbsp; Bs {{ number_format((float) $preSummary['qr_total'], 2) }}</strong></td>
    </tr>
</table>

@if($scope === 'branch')
    <div class="section-title">Resumen por cajero</div>
    <table class="grid" style="margin-bottom: 10px;">
        <thead>
            <tr>
                <th style="width: 38%;">Cajero</th>
                <th style="width: 12%;">Ventas</th>
                <th style="width: 17%;">Total general</th>
                <th style="width: 17%;">Total efectivo</th>
                <th style="width: 16%;">Total QR</th>
            </tr>
        </thead>
        <tbody>
            @forelse($branchGroups as $group)
                <tr>
                    <td>{{ $group['nombre'] }}</td>
                    <td class="center">{{ number_format((int) $group['ventas']) }}</td>
                    <td class="right">Bs {{ number_format((float) $group['total'], 2) }}</td>
                    <td class="right">Bs {{ number_format((float) $group['total_caja'], 2) }}</td>
                    <td class="right">Bs {{ number_format((float) $group['total_qr'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="center" style="padding: 12px;">No se encontraron cajeros con ventas en el rango consultado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if($branchGroups->isNotEmpty())
        @foreach($branchGroups as $cashierIndex => $group)
            @php
                $groupRows = collect($group['rows'] ?? [])->values();
                $groupEffectiveRows = $groupRows
                    ->filter(fn ($row) => strtolower(trim((string) data_get($row, 'metodo_pago', 'efectivo'))) !== 'qr')
                    ->values();
                $groupQrRows = $groupRows
                    ->filter(fn ($row) => strtolower(trim((string) data_get($row, 'metodo_pago', 'efectivo'))) === 'qr')
                    ->values();
            @endphp

            <div class="section-title">Cajero {{ $cashierIndex + 1 }}: {{ strtoupper($group['nombre']) }}</div>
            <table class="summary-grid" style="margin-bottom: 10px;">
                <tr>
                    <td style="width: 25%;"><strong>Total ventas: {{ number_format((int) $group['ventas']) }}</strong></td>
                    <td style="width: 25%;"><strong>Total emitido: Bs {{ number_format((float) $group['total'], 2) }}</strong></td>
                    <td style="width: 25%;"><strong>Efectivo: {{ number_format((int) $groupEffectiveRows->count()) }} &nbsp; Bs {{ number_format((float) $group['total_caja'], 2) }}</strong></td>
                    <td style="width: 25%;"><strong>QR: {{ number_format((int) $groupQrRows->count()) }} &nbsp; Bs {{ number_format((float) $group['total_qr'], 2) }}</strong></td>
                </tr>
            </table>

            @foreach([
                'Ventas efectivo' => [$groupEffectiveRows, (float) $group['total_caja']],
                'Ventas QR' => [$groupQrRows, (float) $group['total_qr']],
            ] as $detailTitle => [$detailRows, $detailTotal])
                @continue($detailRows->isEmpty())
                <div class="section-title">{{ strtoupper($detailTitle) }} - {{ number_format((int) $detailRows->count()) }} - Bs {{ number_format($detailTotal, 2) }}</div>
                <table class="grid" style="margin-bottom: 10px;">
                    <thead>
                        <tr>
                            <th style="width: 4%;">Nro.</th>
                            <th style="width: 14%;">Fecha</th>
                            <th style="width: 10%;">Peso</th>
                            <th style="width: 40%;">Detalle</th>
                            <th style="width: 8%;">Factura</th>
                            <th style="width: 12%;">Estado</th>
                            <th style="width: 12%;">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($detailRows->values() as $index => $row)
                            <tr>
                                <td class="center">{{ $index + 1 }}</td>
                                <td class="center">{{ data_get($row, 'fecha_hora', data_get($row, 'fecha', '-')) }}</td>
                                <td class="right">{{ number_format((float) data_get($row, 'peso', 0), 3) }}</td>
                                <td>
                                    <strong>{{ data_get($row, 'detalle_items', data_get($row, 'tipo_envio', 'Sin detalle')) }}</strong>
                                    @if((string) data_get($row, 'detalle_resumen', '') !== '')
                                        <br>{{ data_get($row, 'detalle_resumen') }}
                                    @endif
                                    @if((string) data_get($row, 'codigo_referencia', '') !== '')
                                        <br>Cod: {{ data_get($row, 'codigo_referencia') }}
                                    @endif
                                </td>
                                <td class="center">{{ data_get($row, 'numero_factura', '-') }}</td>
                                <td class="center" style="white-space: pre-line;">
                                    {{ strtoupper(trim((string) data_get($row, 'estado_label', data_get($row, 'estado_emision', 'PENDIENTE')))) }}
                                </td>
                                <td class="right">Bs {{ number_format((float) data_get($row, 'importe_general', 0), 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endforeach
    @else
        <table class="grid">
            <tbody>
                <tr>
                    <td class="center" style="padding: 12px;">No se encontraron ventas de sucursal con los filtros aplicados.</td>
                </tr>
            </tbody>
        </table>
    @endif

    <table class="totals" style="margin-top: 4px;">
        <tr>
            <td style="width: 89%;" class="right">TOTAL EN CAJA SUCURSAL</td>
            <td style="width: 11%;" class="right">Bs {{ number_format((float) $cashRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
        </tr>
        <tr>
            <td class="right">TOTAL PENDIENTE / NO COBRADO</td>
            <td class="right">Bs {{ number_format((float) $unpaidRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
        </tr>
        <tr>
            <td class="right">TOTAL GENERAL SUCURSAL</td>
            <td class="right">Bs {{ number_format((float) collect($rows)->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
        </tr>
    </table>
@else
    <div class="section-title">Detalle de todas las ventas</div>
    @php
        $detailGroups = [
            'Ventas en efectivo' => $effectiveDetailRows,
            'Ventas QR' => $qrDetailRows,
        ];
    @endphp
    @if(collect($rows)->isNotEmpty())
        @foreach($detailGroups as $detailTitle => $detailRows)
            @continue($detailRows->isEmpty())
            <div class="section-title">{{ $detailTitle }}</div>
            <table class="grid" style="margin-bottom: 10px;">
                <thead>
                    <tr>
                        <th style="width: 4%;">Nro.</th>
                        <th style="width: 14%;">Fecha</th>
                        <th style="width: 10%;">Peso total</th>
                        <th style="width: 40%;">Detalle</th>
                        <th style="width: 8%;">Factura</th>
                        <th style="width: 12%;">Estado</th>
                        <th style="width: 12%;">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($detailRows->values() as $index => $row)
                        <tr>
                            <td class="center">{{ $index + 1 }}</td>
                            <td class="center">{{ data_get($row, 'fecha_hora', data_get($row, 'fecha', '-')) }}</td>
                            <td class="right">{{ number_format((float) data_get($row, 'peso', 0), 3) }}</td>
                            <td>
                                <strong>{{ data_get($row, 'detalle_items', data_get($row, 'tipo_envio', 'Sin detalle')) }}</strong>
                                @if((string) data_get($row, 'detalle_resumen', '') !== '')
                                    <br>{{ data_get($row, 'detalle_resumen') }}
                                @endif
                                @if((string) data_get($row, 'codigo_referencia', '') !== '')
                                    <br>Cod: {{ data_get($row, 'codigo_referencia') }}
                                @endif
                            </td>
                            <td class="center">{{ data_get($row, 'numero_factura', '-') }}</td>
                            <td class="center" style="white-space: pre-line;">
                                {{ strtoupper(trim((string) data_get($row, 'estado_label', data_get($row, 'estado_emision', 'PENDIENTE')))) }}
                            </td>
                            <td class="right">Bs {{ number_format((float) data_get($row, 'importe_general', 0), 2) }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <td colspan="6" class="right" style="font-weight: 700;">TOTAL {{ strtoupper($detailTitle) }}</td>
                        <td class="right" style="font-weight: 700;">Bs {{ number_format((float) $detailRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
                    </tr>
                </tbody>
            </table>
        @endforeach
    @else
        <table class="grid">
            <tbody>
                <tr>
                    <td class="center" style="padding: 12px;">No se encontraron ventas con los filtros aplicados.</td>
                </tr>
            </tbody>
        </table>
    @endif

    <table class="totals" style="margin-top: 0;">
        <tr>
            <td style="width: 89%;" class="right">TOTAL PARCIAL EN CAJA</td>
            <td style="width: 11%;" class="right">Bs {{ number_format((float) $cashRows->sum(fn ($row) => (float) data_get($row, 'importe_parcial', 0)), 2) }}</td>
        </tr>
        <tr>
            <td class="right">TOTAL GENERAL EN CAJA</td>
            <td class="right">Bs {{ number_format((float) $cashRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
        </tr>
        <tr>
            <td class="right">TOTAL QR REFERENCIAL NO SUMADO A CAJA</td>
            <td class="right">Bs {{ number_format((float) $paidRows->filter(fn ($row) => strtolower((string) data_get($row, 'metodo_pago', '')) === 'qr')->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
        </tr>
        <tr>
            <td class="right">TOTAL VENTAS ANULADAS</td>
            <td class="right">Bs {{ number_format((float) $annulledRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
        </tr>
        <tr>
            <td style="width: 89%;" class="right">TOTAL VENTAS NO COBRADAS</td>
            <td style="width: 11%;" class="right">Bs {{ number_format((float) $unpaidRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
        </tr>
    </table>
@endif

<table class="observaciones" style="margin-top: 14px;">
    <tr>
        <td class="obs-label">Observaciones:</td>
        <td class="obs-lines">
            <table class="obs-grid">
                <tr>
                    <td>{{ $observacionFiltros !== '' ? $observacionFiltros : '' }}</td>
                    <td></td>
                </tr>
            </table>
        </td>
    </tr>
</table>

<table class="report-footer">
    <tr>
        <td style="width: 50%;">Emitido por: {{ $user->name }}</td>
        <td style="width: 50%;" class="right">Fecha y hora: {{ $generatedAt->format('d/m/Y h:i a') }}</td>
    </tr>
</table>
</body>
</html>
