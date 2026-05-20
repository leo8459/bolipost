<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura SIAT</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #1f2937; font-size: 12px; }
        .header { margin-bottom: 18px; }
        .title { font-size: 20px; font-weight: bold; color: #0f4c81; }
        .subtitle { color: #6b7280; margin-top: 4px; }
        .section { margin-top: 18px; }
        .section-title { font-size: 14px; font-weight: bold; margin-bottom: 8px; color: #0f4c81; }
        .grid { width: 100%; border-collapse: collapse; }
        .grid td { padding: 6px 8px; border: 1px solid #d1d5db; vertical-align: top; }
        .products { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .products th, .products td { border: 1px solid #d1d5db; padding: 7px 8px; }
        .products th { background: #eef4fb; text-align: left; }
        .text-right { text-align: right; }
        .footer { margin-top: 18px; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">Factura de combustible SIAT</div>
        <div class="subtitle">Copia guardada automaticamente en el sistema Bolipost</div>
    </div>

    <div class="section">
        <div class="section-title">Detalle de la factura</div>
        <table class="grid">
            <tr>
                <td><strong>Numero de factura:</strong> {{ $snapshot['numero_factura'] ?? '-' }}</td>
                <td><strong>CUF:</strong> {{ $snapshot['cuf'] ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Fecha de emision:</strong> {{ $snapshot['fecha_emision'] ?? '-' }}</td>
                <td><strong>Monto total:</strong> Bs {{ number_format((float) ($snapshot['monto_total'] ?? 0), 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Datos del emisor</div>
        <table class="grid">
            <tr>
                <td><strong>NIT emisor:</strong> {{ $snapshot['nit_emisor'] ?? '-' }}</td>
                <td><strong>Razon social:</strong> {{ $snapshot['razon_social_emisor'] ?? '-' }}</td>
            </tr>
            <tr>
                <td colspan="2"><strong>Direccion:</strong> {{ $snapshot['direccion_emisor'] ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Datos del cliente</div>
        <table class="grid">
            <tr>
                <td><strong>Nombre / razon social:</strong> {{ $snapshot['nombre_cliente'] ?? '-' }}</td>
                <td><strong>Factura interna:</strong> {{ $invoice->id ?? '-' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">Detalle de productos</div>
        <table class="products">
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Descripcion</th>
                    <th class="text-right">Cantidad</th>
                    <th class="text-right">Precio unitario</th>
                    <th class="text-right">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                @foreach(($snapshot['details'] ?? []) as $detail)
                    <tr>
                        <td>{{ $detail['codigo'] ?: '-' }}</td>
                        <td>{{ $detail['descripcion'] ?: 'Combustible' }}</td>
                        <td class="text-right">{{ $detail['cantidad'] ?? '-' }}</td>
                        <td class="text-right">Bs {{ number_format((float) ($detail['precio_unitario'] ?? 0), 2) }}</td>
                        <td class="text-right">Bs {{ number_format((float) ($detail['subtotal'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="footer">
        Fuente SIAT:
        {{ $snapshot['siat_source_url'] ?? '-' }}
    </div>
</body>
</html>
