<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kardex de mantenimiento</title>
    <style>
<<<<<<< Updated upstream
        @php
            $logoPath = public_path('images/AGBClogo1.png');
            $logoData = file_exists($logoPath)
                ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
                : null;

            $headerYear = (string) ($vehicle->anio ?? optional($generatedAt)->format('Y') ?? now()->format('Y'));
            $currentKm = $vehicle->kilometraje_actual ?? $vehicle->kilometraje_inicial ?? $vehicle->kilometraje ?? null;
            $driverName = trim((string) ($assignedDriver?->nombre ?? ''));
            $driverName = $driverName !== '' ? $driverName : '-';
            $regionalLabel = trim((string) ($regional ?? '')) !== '' ? $regional : '-';
            $rows = $maintenanceLogs->values();
            $minBlankRows = 8;
            $blankRows = max($minBlankRows - $rows->count(), 0);
        @endphp

        @page {
            margin: 26px 24px 32px;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            color: #222;
            font-size: 10.5px;
        }

        .page {
            width: 100%;
        }

        .topbar {
            width: 100%;
            margin-bottom: 10px;
        }

        .topbar td {
            vertical-align: middle;
        }

        .brand-left {
            font-size: 10px;
            color: #666;
            line-height: 1.2;
            width: 32%;
        }

        .brand-center {
            width: 36%;
            text-align: center;
        }

        .brand-right {
            width: 32%;
            text-align: right;
        }

        .brand-right img {
            max-width: 150px;
            max-height: 52px;
        }

        .title {
            text-align: center;
            font-size: 16px;
            letter-spacing: 0.5px;
            margin: 6px 0 12px;
            font-weight: bold;
        }

        .meta-table {
=======
        @page {
            margin: 24px 26px 30px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #444;
            font-size: 11px;
        }

        .header-table,
        .info-table,
        .report-table,
        .signature-table {
>>>>>>> Stashed changes
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 10px;
        }

<<<<<<< Updated upstream
        .meta-table td {
            padding: 2px 4px;
            vertical-align: bottom;
        }

        .label {
            width: 10%;
            white-space: nowrap;
            color: #555;
            font-weight: bold;
        }

        .line-cell {
            border-bottom: 1px solid #666;
            height: 16px;
            color: #222;
        }

        .line-cell.long {
            height: 18px;
        }

        .kardex-table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin-top: 8px;
        }

        .kardex-table th,
        .kardex-table td {
            border: 1px solid #8a8a8a;
            padding: 5px 4px;
=======
        .header-table td {
>>>>>>> Stashed changes
            vertical-align: top;
            word-wrap: break-word;
        }

<<<<<<< Updated upstream
        .kardex-table thead th {
            text-align: center;
            font-weight: bold;
            background: #efefef;
        }

        .kardex-table thead .group-title {
            font-size: 11px;
            padding: 8px 4px;
        }

        .row-date { width: 12%; }
        .row-km { width: 11%; }
        .row-service { width: 27%; }
        .row-workshop { width: 13%; }
        .row-unit { width: 10%; }
        .row-invoice { width: 10%; }
        .row-amount { width: 11%; }
        .row-observation { width: 16%; }

        .data-row td {
            height: 34px;
        }

        .text-right {
            text-align: right;
        }

=======
        .logo-left {
            width: 165px;
            text-align: left;
        }

        .logo-right {
            width: 165px;
            text-align: right;
        }

        .logo-left img,
        .logo-right img {
            max-width: 145px;
            max-height: 52px;
        }

        .title-wrap {
            text-align: center;
            vertical-align: middle !important;
        }

        .title {
            margin: 22px 0 0;
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.2px;
            text-transform: uppercase;
        }

        .info-table {
            margin-top: 10px;
        }

        .info-table td {
            padding: 1px 4px;
            vertical-align: bottom;
        }

        .info-label {
            width: 68px;
            font-size: 10px;
            text-transform: uppercase;
        }

        .line-cell {
            border-bottom: 1px solid #777;
            height: 16px;
            font-size: 10px;
        }

        .report-table {
            margin-top: 12px;
            table-layout: fixed;
        }

        .report-table th,
        .report-table td {
            border: 1px solid #888;
            padding: 5px 4px;
            vertical-align: top;
            word-wrap: break-word;
        }

        .report-table thead th {
            background: #d9d9d9;
            text-transform: uppercase;
            font-size: 10px;
            text-align: center;
            font-weight: bold;
        }

        .section-title {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.2px;
        }

        .col-fecha { width: 8%; }
        .col-km { width: 11%; }
        .col-desc { width: 28%; }
        .col-taller { width: 12%; }
        .col-unidad { width: 9%; }
        .col-factura { width: 9%; }
        .col-importe { width: 10%; }
        .col-obs { width: 13%; }

        .row-empty td,
        .row-data td {
            height: 24px;
            font-size: 10px;
        }

>>>>>>> Stashed changes
        .text-center {
            text-align: center;
        }

<<<<<<< Updated upstream
        .muted {
            color: #666;
        }

        .signature-wrap {
            width: 100%;
            margin-top: 34px;
            text-align: center;
        }

        .signature-line {
            width: 44%;
            margin: 0 auto 6px;
            border-bottom: 1px solid #666;
            height: 14px;
        }

        .signature-label {
            font-size: 11px;
            color: #555;
            font-weight: bold;
=======
        .text-right {
            text-align: right;
        }

        .signature-table {
            margin-top: 34px;
        }

        .signature-line {
            width: 58%;
            border-top: 1px solid #555;
            padding-top: 4px;
            font-size: 10px;
            text-transform: uppercase;
        }

        .muted {
            color: #666;
>>>>>>> Stashed changes
        }
    </style>
</head>
<body>
<<<<<<< Updated upstream
    <div class="page">
        <table class="topbar">
            <tr>
                <td class="brand-left">
                    <div>ESTADO PLURINACIONAL</div>
                    <div>OBRAS PUBLICAS,</div>
                    <div>SERVICIOS Y VIVIENDA</div>
                </td>
                <td class="brand-center"></td>
                <td class="brand-right">
                    @if($logoData)
                        <img src="{{ $logoData }}" alt="Correos de Bolivia">
                    @else
                        <div style="font-size: 18px; font-weight: bold; color: #777;">CORREOS DE BOLIVIA</div>
                    @endif
                </td>
            </tr>
        </table>

        <div class="title">KARDEX DE MANTENIMIENTO DE VEHICULOS GESTION {{ $headerYear }}</div>

        <table class="meta-table">
            <tr>
                <td class="label">REGIONAL:</td>
                <td class="line-cell">{{ $regionalLabel }}</td>
                <td class="label">ANO:</td>
                <td class="line-cell">{{ $vehicle->anio ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">PLACA:</td>
                <td class="line-cell">{{ $vehicle->placa ?? '-' }}</td>
                <td class="label">CHASIS:</td>
                <td class="line-cell">{{ $vehicle->chasis ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">MARCA:</td>
                <td class="line-cell">{{ $vehicle->brand?->nombre ?? $vehicle->marca ?? '-' }}</td>
                <td class="label">MODELO:</td>
                <td class="line-cell">{{ $vehicle->modelo ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">MOTOR:</td>
                <td class="line-cell" colspan="3">{{ $vehicle->motor ?? '-' }}</td>
            </tr>
            <tr>
                <td class="label">CONDUCTOR ASIGNADO:</td>
                <td class="line-cell long" colspan="3">{{ $driverName }}</td>
            </tr>
        </table>

        <table class="kardex-table">
            <thead>
                <tr>
                    <th class="row-date" rowspan="2">FECHA</th>
                    <th class="row-km" rowspan="2">KILOMETRAJE</th>
                    <th class="group-title" colspan="6">MANTENIMIENTO PREVENTIVO Y CORRECTIVO</th>
                </tr>
                <tr>
                    <th class="row-service">DESCRIPCION DE SERVICIO</th>
                    <th class="row-workshop">EMPRESA O TALLER</th>
                    <th class="row-unit">UNIDAD DE MEDIDA</th>
                    <th class="row-invoice">NUMERO DE FACTURA</th>
                    <th class="row-amount">IMPORTE</th>
                    <th class="row-observation">OBSERVACIONES</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $log)
                    @php
                        $serviceName = trim((string) ($log->maintenanceType?->nombre ?? $log->tipo ?? ''));
                        $serviceDescription = trim((string) ($log->descripcion ?? ''));
                        $serviceText = $serviceName !== '' && $serviceDescription !== ''
                            ? $serviceName . ': ' . $serviceDescription
                            : ($serviceName !== '' ? $serviceName : ($serviceDescription !== '' ? $serviceDescription : '-'));

                        $invoiceValue = trim((string) ($log->comprobante ?? ''));
                        if ($invoiceValue !== '' && str_contains($invoiceValue, '/')) {
                            $invoiceValue = basename($invoiceValue);
                        }
                    @endphp
                    <tr class="data-row">
                        <td class="text-center">{{ optional($log->fecha)->format('d/m/Y') ?? '-' }}</td>
                        <td class="text-right">{{ $log->kilometraje !== null ? number_format((float) $log->kilometraje, 2) : ($currentKm !== null ? number_format((float) $currentKm, 2) : '-') }}</td>
                        <td>{{ $serviceText }}</td>
                        <td>{{ $log->taller ?? '-' }}</td>
                        <td class="text-center">{{ trim((string) ($log->unidad_medida ?? '')) !== '' ? $log->unidad_medida : 'Servicio' }}</td>
                        <td class="text-center">{{ $invoiceValue !== '' ? $invoiceValue : '-' }}</td>
                        <td class="text-right">{{ $log->costo !== null ? 'Bs ' . number_format((float) $log->costo, 2) : '-' }}</td>
                        <td>{{ $log->observaciones ?: '-' }}</td>
                    </tr>
                @empty
                    <tr class="data-row">
                        <td class="text-center muted">-</td>
                        <td class="text-center muted">-</td>
                        <td class="muted">Sin mantenimientos registrados.</td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>
                @endforelse

                @for($i = 0; $i < $blankRows; $i++)
                    <tr class="data-row">
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                @endfor
            </tbody>
        </table>

        <div class="signature-wrap">
            <div class="signature-line"></div>
            <div class="signature-label">FIRMA Y SELLO DEL CONDUCTOR DESIGNADO</div>
        </div>
    </div>
=======
@php
    $logoPath = public_path('images/AGBClogo1.png');
    $logoData = file_exists($logoPath)
        ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath))
        : null;

    $rows = collect($maintenanceLogs ?? [])->map(function ($log) {
        return [
            'fecha' => optional($log->fecha)->format('d/m/Y') ?? '',
            'kilometraje' => $log->kilometraje !== null ? number_format((float) $log->kilometraje, 2) : '',
            'descripcion' => (string) ($log->maintenanceType?->nombre ?? $log->tipo ?? $log->descripcion ?? ''),
            'taller' => (string) ($log->taller ?? ''),
            'unidad' => 'Servicio',
            'factura' => (string) ($log->comprobante ?? ''),
            'importe' => $log->costo !== null ? number_format((float) $log->costo, 2) : '',
            'observaciones' => (string) ($log->observaciones ?: ($log->descripcion ?: '')),
        ];
    })->values();

    $minimumRows = max(10, $rows->count());
    $displayRows = collect(range(0, $minimumRows - 1))->map(fn ($index) => $rows->get($index));

    $assignedDriver = trim((string) ($currentAssignment?->driver?->nombre ?? ''));
    $displayMarca = trim((string) ($vehicle->brand?->nombre ?? $vehicle->marca ?? ''));
    $displayModel = trim((string) ($vehicle->modelo ?? ''));
    $displayYear = trim((string) ($vehicle->anio ?? ''));
    $displayChasis = trim((string) ($vehicle->chasis ?? ''));
@endphp

    <table class="header-table">
        <tr>
            <td class="logo-left">
                @if($logoData)
                    <img src="{{ $logoData }}" alt="Correos de Bolivia">
                @endif
            </td>
            <td class="title-wrap">
                <div class="title">Kardex de mantenimiento de vehiculos gestion {{ now()->year }}</div>
            </td>
            <td class="logo-right">
                @if($logoData)
                    <img src="{{ $logoData }}" alt="Correos de Bolivia">
                @endif
            </td>
        </tr>
    </table>

    <table class="info-table">
        <tr>
            <td class="info-label">Regional:</td>
            <td class="line-cell">{{ $regional ?? '-' }}</td>
            <td class="info-label">Año:</td>
            <td class="line-cell">{{ $displayYear !== '' ? $displayYear : '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Placa:</td>
            <td class="line-cell">{{ $vehicle->placa ?? '-' }}</td>
            <td class="info-label">Chasis:</td>
            <td class="line-cell">{{ $displayChasis !== '' ? $displayChasis : '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Marca:</td>
            <td class="line-cell">{{ $displayMarca !== '' ? $displayMarca : '-' }}</td>
            <td class="info-label">Modelo:</td>
            <td class="line-cell">{{ $displayModel !== '' ? $displayModel : '-' }}</td>
        </tr>
        <tr>
            <td class="info-label">Conductor asignado:</td>
            <td class="line-cell" colspan="3">{{ $assignedDriver !== '' ? $assignedDriver : '-' }}</td>
        </tr>
    </table>

    <table class="report-table">
        <thead>
            <tr>
                <th class="col-fecha" rowspan="2">Fecha</th>
                <th class="col-km" rowspan="2">Kilometraje</th>
                <th colspan="6" class="section-title">Mantenimiento preventivo y correctivo</th>
            </tr>
            <tr>
                <th class="col-desc">Descripcion de servicio</th>
                <th class="col-taller">Empresa o taller</th>
                <th class="col-unidad">Unidad de medida</th>
                <th class="col-factura">Numero de factura</th>
                <th class="col-importe">Importe</th>
                <th class="col-obs">Observaciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($displayRows as $row)
                <tr class="{{ $row ? 'row-data' : 'row-empty' }}">
                    <td class="text-center">{{ $row['fecha'] ?? '' }}</td>
                    <td class="text-right">{{ $row['kilometraje'] ?? '' }}</td>
                    <td>{{ $row['descripcion'] ?? '' }}</td>
                    <td>{{ $row['taller'] ?? '' }}</td>
                    <td class="text-center">{{ $row['unidad'] ?? '' }}</td>
                    <td class="text-center">{{ $row['factura'] ?? '' }}</td>
                    <td class="text-right">{{ isset($row['importe']) && $row['importe'] !== '' ? 'Bs ' . $row['importe'] : '' }}</td>
                    <td>{{ $row['observaciones'] ?? '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="signature-table">
        <tr>
            <td class="signature-line">Firma y sello del conductor designado</td>
            <td></td>
        </tr>
    </table>
>>>>>>> Stashed changes
</body>
</html>
