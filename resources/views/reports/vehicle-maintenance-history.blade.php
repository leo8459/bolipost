<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kardex de mantenimiento</title>
    <style>
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
            width: 100%;
            border-collapse: collapse;
        }

        .header-table td {
            vertical-align: top;
        }

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

        .text-center {
            text-align: center;
        }

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
        }
    </style>
</head>
<body>
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
</body>
</html>
