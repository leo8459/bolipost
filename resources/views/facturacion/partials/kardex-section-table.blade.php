<div class="section-title">{{ $section['title'] }}</div>
<table class="grid">
    <thead>
        <tr>
            <th style="width: 4%;">Nro.</th>
            <th style="width: 10%;">FECHA</th>
            <th style="width: 18%;">TIPO DE VENTA / ENVIO</th>
            <th style="width: 14%;">EMISION</th>
            <th style="width: 19%;">ORDEN / PAQUETES</th>
            <th style="width: 12%;">PESO TOTAL</th>
            <th style="width: 8%;">CANTIDAD</th>
            <th style="width: 12%;">Nro. FACTURA</th>
            <th style="width: 11%;">IMPORTE</th>
        </tr>
    </thead>
    <tbody>
        @foreach($sectionRows as $index => $row)
            <tr>
                <td class="center">{{ $index + 1 }}</td>
                <td class="center">{{ $row['fecha'] }}</td>
                <td>{{ $row['tipo_envio'] }}</td>
                <td>{{ $row['emision_label'] }}</td>
                <td style="white-space: pre-line;">{{ $row['codigo_referencia'] ?? $row['codigo_item'] }}</td>
                <td class="right">{{ number_format((float) $row['peso'], 3) }}</td>
                <td class="center">{{ $row['cantidad'] }}</td>
                <td class="center">{{ $row['numero_factura'] }}</td>
                <td class="right">{{ number_format((float) $row['importe_general'], 2) }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="8" class="right" style="font-weight: 700;">{{ $section['total_label'] }}</td>
            <td class="right" style="font-weight: 700;">Bs {{ number_format((float) $sectionRows->sum(fn ($row) => (float) data_get($row, 'importe_general', 0)), 2) }}</td>
        </tr>
    </tbody>
</table>
