<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Entregas diarias de EMS y Contratos</title>
    <style>
        @page {
            margin: 28pt 34pt 40pt;
        }

        body {
            color: #293a4e;
            font: 9pt DejaVu Sans, sans-serif;
        }

        .report-header {
            width: 100%;
            border-collapse: collapse;
            border-bottom: 2pt solid #164b82;
        }

        .report-header td {
            padding: 0 0 10pt;
            vertical-align: middle;
        }

        .report-header .report-logo {
            width: 90pt;
            padding-right: 14pt;
        }

        .report-logo img {
            width: 90pt;
        }

        .report-brand {
            color: #48617c;
            font-size: 8pt;
        }

        h1 {
            margin: 2pt 0 0;
            color: #153e69;
            font-size: 20pt;
        }

        h3 {
            margin: 13pt 0 5pt;
            color: #153e69;
            font-size: 12pt;
            page-break-after: avoid;
        }

        .delivery-report-cutoff {
            margin: 8pt 0 0;
        }

        .delivery-report-summary th,
        .delivery-report-summary td,
        .delivery-report-couriers th,
        .delivery-report-couriers td {
            border: 0.5pt solid #d5dfed;
            padding: 4pt 6pt;
        }

        .delivery-report-summary tbody th,
        .delivery-report-summary tfoot th,
        .delivery-report-couriers tbody th {
            text-align: left;
        }

        .delivery-report-summary thead th,
        .delivery-report-couriers thead th {
            background: #e6effa;
            color: #153e69;
        }

        .delivery-report-summary tfoot th,
        .delivery-report-summary tfoot td {
            background: #e6effa;
            color: #153e69;
            font-weight: bold;
        }

        .delivery-report-couriers {
            font-size: 8pt;
        }

        .delivery-courier-name {
            width: 26%;
        }

        .delivery-courier-department {
            width: 15%;
        }

        .delivery-report-couriers thead {
            display: table-header-group;
        }

        .delivery-report-couriers tr {
            page-break-inside: avoid;
        }

        .delivery-report-couriers tbody tr:nth-child(even) > * {
            background: #f5f8fc;
        }

        .delivery-report-note {
            margin: 5pt 0 0;
            color: #50647b;
            font-size: 8pt;
        }

        .report-footer {
            position: fixed;
            right: 0;
            bottom: -23pt;
            left: 0;
            border-top: 0.5pt solid #d5dfed;
            padding-top: 5pt;
            color: #50647b;
            font-size: 8pt;
        }
    </style>
</head>
<body>
    <div class="report-footer">Correos de Bolivia · Entregas diarias</div>

    @php($logoPath = public_path('images/AGBClogo1.png'))
    <table class="report-header">
        <tr>
            @if (is_file($logoPath))
                <td class="report-logo">
                    <img alt="Correos de Bolivia" src="data:image/png;base64,{{ base64_encode(file_get_contents($logoPath)) }}">
                </td>
            @endif
            <td>
                <div class="report-brand">Correos de Bolivia</div>
                <h1>Entregas diarias de EMS y Contratos</h1>
            </td>
        </tr>
    </table>

    @include('emails.partials.daily-deliveries-report')
</body>
</html>
