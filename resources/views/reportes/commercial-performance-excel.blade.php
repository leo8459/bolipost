<table>
    <tr>
        <td colspan="8"><strong>{{ $scopeLabel }}</strong></td>
    </tr>
    <tr>
        <td colspan="8">Rango: {{ !empty($from) || !empty($to) ? (($from ?: 'inicio') . ' - ' . ($to ?: 'fin')) : 'todos' }}</td>
    </tr>
    <tr>
        <td colspan="8">Lineas: {{ !empty($selectedLines) ? implode(', ', $selectedLines) : 'Todas' }}</td>
    </tr>
    <tr>
        <td colspan="8">Fecha de emision: {{ now()->format('d/m/Y H:i') }}</td>
    </tr>
    <tr><td colspan="8"></td></tr>
    <tr>
        <td colspan="8"><strong>Ranking por linea de negocio</strong></td>
    </tr>
    <tr>
        <th>#</th>
        <th>Linea de negocio</th>
        <th>Cantidad</th>
        <th>Entregados</th>
        <th>No entregados</th>
        <th>Peso</th>
        <th>Bs</th>
        <th>Servicio lider</th>
    </tr>
    @foreach(($lineRows ?? []) as $index => $lineRow)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $lineRow['linea'] }}</td>
            <td>{{ (int) $lineRow['cantidad'] }}</td>
            <td>{{ (int) $lineRow['entregados'] }}</td>
            <td>{{ (int) $lineRow['no_entregados'] }}</td>
            <td>{{ (float) $lineRow['peso'] }}</td>
            <td>{{ (float) $lineRow['precio'] }}</td>
            <td>{{ $lineRow['top_servicio'] }}</td>
        </tr>
    @endforeach
    <tr><td colspan="8"></td></tr>
    <tr>
        <td colspan="8"><strong>Detalle por servicio</strong></td>
    </tr>
    <tr>
        <th>#</th>
        <th>Linea</th>
        <th>Servicio</th>
        <th>Cantidad</th>
        <th>Entregados</th>
        <th>No entregados</th>
        <th>Peso</th>
        <th>Bs</th>
    </tr>
    @foreach(($serviceRows ?? []) as $index => $serviceRow)
        <tr>
            <td>{{ $index + 1 }}</td>
            <td>{{ $serviceRow['linea'] }}</td>
            <td>{{ $serviceRow['servicio'] }}</td>
            <td>{{ (int) $serviceRow['cantidad'] }}</td>
            <td>{{ (int) $serviceRow['entregados'] }}</td>
            <td>{{ (int) $serviceRow['no_entregados'] }}</td>
            <td>{{ (float) $serviceRow['peso'] }}</td>
            <td>{{ (float) $serviceRow['precio'] }}</td>
        </tr>
    @endforeach
</table>
