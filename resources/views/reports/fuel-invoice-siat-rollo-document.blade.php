@php
    $details = is_array($snapshot['details'] ?? null) ? $snapshot['details'] : [];
    $firstDetail = is_array($details[0] ?? null) ? $details[0] : [];
    $amount = $snapshot['monto_total'] ?? $invoice->monto_total ?? 0;
    $cantidad = $firstDetail['cantidad'] ?? $snapshot['cantidad'] ?? '';
    $precio = $firstDetail['precio_unitario'] ?? $snapshot['precio_unitario'] ?? '';
    $subtotal = $firstDetail['subtotal'] ?? $snapshot['monto_total'] ?? '';
    $codigo = $firstDetail['codigo'] ?? '';
    $descripcion = $firstDetail['descripcion'] ?? 'Combustible';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Factura Rollo</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #111827;
            margin: 0;
            padding: 18px 16px;
        }
        .ticket {
            width: 100%;
            margin: 0 auto;
            text-align: center;
        }
        .title {
            font-weight: 800;
            font-size: 15px;
            margin-bottom: 2px;
        }
        .strong {
            font-weight: 800;
        }
        .divider {
            border-top: 1px dashed #111827;
            margin: 10px 0;
        }
        .block {
            margin-bottom: 6px;
            line-height: 1.3;
        }
        .row {
            text-align: left;
            margin-bottom: 4px;
        }
        .label {
            font-weight: 800;
        }
        .amounts .row {
            display: block;
        }
        .muted {
            font-size: 10px;
        }
        .qr-box {
            margin-top: 12px;
            font-size: 10px;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="ticket">
        <div class="title">FACTURA</div>
        <div class="strong">CON DERECHO A CREDITO FISCAL</div>
        <div class="block">{{ $snapshot['razon_social_emisor'] ?? 'SIN EMISOR' }}</div>
        <div class="block">{{ $snapshot['direccion_emisor'] ?? 'SIN DIRECCION' }}</div>

        <div class="divider"></div>

        <div class="row"><span class="label">NIT:</span> {{ $snapshot['nit_emisor'] ?? '-' }}</div>
        <div class="row"><span class="label">FACTURA N:</span> {{ $snapshot['numero_factura'] ?? $invoice->numero_factura ?? '-' }}</div>
        <div class="row"><span class="label">COD. AUTORIZACION:</span> {{ $snapshot['cuf'] ?? '-' }}</div>
        <div class="row"><span class="label">NOMBRE/RAZON SOCIAL:</span> {{ $snapshot['nombre_cliente'] ?? $invoice->nombre_cliente ?? '-' }}</div>
        <div class="row"><span class="label">FECHA DE EMISION:</span> {{ $snapshot['fecha_emision'] ?? optional($invoice->fecha_emision)->format('d/m/Y H:i:s') ?? '-' }}</div>

        <div class="divider"></div>

        <div class="strong">DETALLE</div>
        <div class="row">{{ $codigo !== '' ? $codigo . ' - ' : '' }}{{ $descripcion }}</div>
        <div class="row muted">Unidad de Medida: Litro</div>
        <div class="row">{{ $cantidad !== '' ? $cantidad : '0' }} X {{ $precio !== '' ? $precio : '0' }} - {{ $subtotal !== '' ? $subtotal : '0' }}</div>

        <div class="divider"></div>

        <div class="amounts">
            <div class="row"><span class="label">SUBTOTAL Bs</span> {{ $subtotal !== '' ? $subtotal : number_format((float) $amount, 2) }}</div>
            <div class="row"><span class="label">DESCUENTO Bs</span> 0.00</div>
            <div class="row"><span class="label">TOTAL Bs</span> {{ number_format((float) $amount, 2) }}</div>
            <div class="row"><span class="label">IMPORTE BASE CF MONTO LEY 317</span> {{ number_format((float) $amount * 0.7, 2) }}</div>
        </div>

        <div class="divider"></div>

        <div class="block">Este documento es una representacion grafica basada en los datos SIAT extraidos por el sistema.</div>
        @if(!empty($snapshot['siat_source_url']))
            <div class="qr-box">{{ $snapshot['siat_source_url'] }}</div>
        @endif
    </div>
</body>
</html>
