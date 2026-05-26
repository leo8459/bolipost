<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Registro de entregas de correspondencia a domicilio</title>
    <style>
        @page { size: A4; margin: 10mm; }
        body { font-family: Verdana, DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        .header-title { text-align: center; font-size: 19px; font-weight: 700; margin: 8px 0 14px; }
        .meta-table, .detail-table, .summary-table { width: 100%; border-collapse: collapse; }
        .meta-table td { padding: 4px 2px; vertical-align: top; }
        .detail-table th, .detail-table td,
        .summary-table td, .summary-table th {
            border: 1px solid #666;
            padding: 4px;
            vertical-align: top;
        }
        .detail-table th, .summary-table th {
            background: #f1f1f1;
            text-align: center;
            font-weight: 700;
        }
        .barcode { text-align: center; min-width: 160px; }
        .barcode-code { margin-top: 4px; font-size: 10px; font-weight: 700; letter-spacing: 0.6px; }
        .center { text-align: center; }
        .signatures { width: 100%; margin-top: 28px; }
        .sign-box { width: 42%; display: inline-block; text-align: center; vertical-align: top; }
        .sign-line { border-top: 1px solid #444; margin: 34px auto 6px; width: 78%; }
        .muted { color: #444; }
        .w-no { width: 4%; }
        .w-code { width: 32%; }
        .w-dest { width: 18%; }
        .w-dir { width: 18%; }
        .w-peso { width: 7%; }
        .w-firma { width: 14%; }
        .w-cobro { width: 7%; }
    </style>
</head>
<body>
    @php
        $assignedAtText = optional($assigned_at ?? null)->format('Y-m-d H:i') ?? now()->format('Y-m-d H:i');
        $summary = [
            'EMS' => (int) ($summary_by_type['EMS'] ?? 0),
            'CERTI' => (int) ($summary_by_type['CERTI'] ?? 0),
            'ORDI' => (int) ($summary_by_type['ORDI'] ?? 0),
            'CONTRATO' => (int) ($summary_by_type['CONTRATO'] ?? 0),
        ];
    @endphp

    <div class="header-title">Registro de entregas de Correspondencia a Domicilio</div>

    <table class="meta-table">
        <tr>
            <td><strong>Nombre del Distribuidor:</strong> {{ $assigned_user->name ?? 'Sin distribuidor' }}</td>
            <td class="center"><strong>Regional:</strong> {{ $regional ?? 'SIN REGIONAL' }}</td>
        </tr>
        <tr>
            <td><strong>Fecha:</strong> {{ $assignedAtText }}</td>
            <td></td>
        </tr>
    </table>

    <table class="detail-table" style="margin-top:10px;">
        <thead>
            <tr>
                <th class="w-no">No</th>
                <th class="w-code">Codigo Rastreo</th>
                <th class="w-dest">Destinatario</th>
                <th class="w-dir">Direccion</th>
                <th class="w-peso">Peso (Kg.)</th>
                <th class="w-firma">Firma/Sello Destinatario</th>
                <th class="w-cobro">Cobro (Bs.)</th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td class="center">{{ $row['no'] ?? '' }}</td>
                    <td class="barcode">
                        @php
                            $codigo = (string) ($row['codigo'] ?? '');
                        @endphp
                        @if($codigo !== '' && class_exists('\DNS1D'))
                            {!! DNS1D::getBarcodeHTML($codigo, 'C128', 1.15, 26) !!}
                        @endif
                        <div class="barcode-code">{{ $codigo }}</div>
                    </td>
                    <td>{{ $row['destinatario'] ?? '' }}</td>
                    <td>{{ $row['direccion'] ?? '' }}</td>
                    <td class="center">{{ is_numeric($row['peso'] ?? null) ? number_format((float) $row['peso'], 3, '.', '') : '' }}</td>
                    <td></td>
                    <td></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="center">No hay paquetes asignados para este reporte.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary-table" style="margin-top:16px; width:72%; margin-left:auto; margin-right:auto;">
        <thead>
            <tr>
                <th></th>
                <th>EMS</th>
                <th>CERTIFICADO</th>
                <th>ORDINARIO</th>
                <th>CONTRATO</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><strong>TOTAL ASIGNADOS</strong></td>
                <td class="center">{{ $summary['EMS'] }}</td>
                <td class="center">{{ $summary['CERTI'] }}</td>
                <td class="center">{{ $summary['ORDI'] }}</td>
                <td class="center">{{ $summary['CONTRATO'] }}</td>
            </tr>
            <tr>
                <td><strong>TOTAL ENVIOS LLEVADOS</strong></td>
                <td colspan="4" class="center">{{ $total_assigned ?? count($rows ?? []) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="signatures">
        <div class="sign-box">
            <div class="sign-line"></div>
            <div><strong>SUPERVISOR/SALIDA</strong></div>
            <div class="muted">{{ $actor_user->name ?? 'Sin supervisor' }}</div>
        </div>
        <div class="sign-box" style="float:right;">
            <div class="sign-line"></div>
            <div><strong>ENTREGADO POR</strong></div>
            <div class="muted">{{ $assigned_user->name ?? 'Sin distribuidor' }}</div>
        </div>
    </div>
</body>
</html>
