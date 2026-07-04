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
    </style>
</head>
<body>
@php
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
    $unpaidSectionKeys = ['qr_pendiente', 'qr_cancelado'];
    $hasPaidRows = collect($paidSectionKeys)->contains(fn ($key) => $rowsByChannel->get($key, collect())->isNotEmpty());
    $hasUnpaidRows = collect($unpaidSectionKeys)->contains(fn ($key) => $rowsByChannel->get($key, collect())->isNotEmpty());
    $paidRows = collect($rows)->filter(fn ($row) => in_array((string) data_get($row, 'section_key', ''), $paidSectionKeys, true))->values();
    $unpaidRows = collect($rows)->filter(fn ($row) => in_array((string) data_get($row, 'section_key', ''), $unpaidSectionKeys, true))->values();
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
        <td class="field">Nombre Responsable:</td>
        <td class="value">{{ $user->name }}</td>
    </tr>
    <tr>
        <td class="field">Ventanilla:</td>
        <td class="value">{{ $ventanilla !== '' ? $ventanilla : '-' }}</td>
        <td class="field">Fecha de recaudacion:</td>
        <td class="value">{{ $fechaRecaudacion }}</td>
    </tr>
</table>

<div class="section-title">Kardex de ventas cobradas</div>
@if($hasPaidRows)
    @foreach($paidSectionKeys as $channelKey)
        @php($sectionRows = $rowsByChannel->get($channelKey, collect())->values())
        @continue($sectionRows->isEmpty())
        @include('facturacion.partials.kardex-section-table', ['section' => $sectionConfigs[$channelKey], 'sectionRows' => $sectionRows])
    @endforeach
@else
    <table class="grid">
        <tbody>
            <tr>
                <td class="center" style="padding: 12px;">No se encontraron ventas cobradas con los filtros aplicados.</td>
            </tr>
        </tbody>
    </table>
@endif

<table class="totals" style="margin-top: 0;">
    <tr>
        <td style="width: 89%;" class="right">TOTAL PARCIAL EN CAJA</td>
        <td style="width: 11%;" class="right">Bs {{ number_format((float) $paidRows->sum(fn ($row) => (float) data_get($row, 'importe_parcial', 0)), 2) }}</td>
    </tr>
    <tr>
        <td class="right">TOTAL GENERAL EN CAJA</td>
        <td class="right">Bs {{ number_format((float) $paidRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
    </tr>
    <tr>
        <td class="right">TOTAL QR REFERENCIAL NO SUMADO A CAJA</td>
        <td class="right">Bs {{ number_format((float) $paidRows->filter(fn ($row) => strtolower((string) data_get($row, 'metodo_pago', '')) === 'qr')->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
    </tr>
</table>

<div class="page-break"></div>
<div class="section-title">Kardex de ventas no cobradas</div>
@if($hasUnpaidRows)
    @foreach($unpaidSectionKeys as $channelKey)
        @php($sectionRows = $rowsByChannel->get($channelKey, collect())->values())
        @continue($sectionRows->isEmpty())
        @include('facturacion.partials.kardex-section-table', ['section' => $sectionConfigs[$channelKey], 'sectionRows' => $sectionRows])
    @endforeach
@else
    <table class="grid">
        <tbody>
            <tr>
                <td class="center" style="padding: 12px;">No se encontraron ventas no cobradas con los filtros aplicados.</td>
            </tr>
        </tbody>
    </table>
@endif

<table class="totals" style="margin-top: 0;">
    <tr>
        <td style="width: 89%;" class="right">TOTAL VENTAS NO COBRADAS</td>
        <td style="width: 11%;" class="right">Bs {{ number_format((float) $unpaidRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
    </tr>
</table>

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
</body>
</html>
