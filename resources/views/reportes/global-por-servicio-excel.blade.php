<table>
    <tr>
        <td colspan="10"><strong>{{ strtoupper($scopeLabel) }}</strong></td>
    </tr>
    <tr>
        <td colspan="10">Generado: {{ now()->format('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="10">Módulos: {{ implode(', ', $moduleLabels) }}</td>
    </tr>
    <tr>
        <td colspan="10">Recepción: {{ !empty($selectedReceptionChannels) ? implode(', ', $selectedReceptionChannels) : 'Todas' }}</td>
    </tr>
    <tr>
        <td colspan="10">Servicios filtrados: {{ !empty($selectedServices) ? implode(', ', $selectedServices) : 'Todos' }}</td>
    </tr>
    <tr>
        <td colspan="10">
            @if(!empty($selectedMonthLabels))
                Meses: {{ implode(', ', $selectedMonthLabels) }}
            @elseif(!empty($from) || !empty($to))
                Rango: {{ $from ?: 'inicio' }} - {{ $to ?: 'fin' }}
            @else
                Rango: todos
            @endif
        </td>
    </tr>
</table>

<table>
    <tr><td colspan="10"></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Servicio</th>
            <th>Cantidad</th>
            <th>Entregados</th>
            <th>No entregados</th>
            <th>Peso</th>
            <th>Bs</th>
            <th>Recepción</th>
            <th>Módulos</th>
            <th>Último registro</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($serviceRows ?? []) as $idx => $serviceRow)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $serviceRow['servicio'] }}</td>
                <td>{{ (int) $serviceRow['cantidad'] }}</td>
                <td>{{ (int) $serviceRow['entregados'] }}</td>
                <td>{{ (int) $serviceRow['no_entregados'] }}</td>
                <td>{{ (float) $serviceRow['peso'] }}</td>
                <td>{{ (float) $serviceRow['precio'] }}</td>
                <td>{{ $serviceRow['canales_texto'] ?: '-' }}</td>
                <td>{{ $serviceRow['modulos_texto'] ?: '-' }}</td>
                <td>{{ $serviceRow['ultimo_registro'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="10">Sin resultados para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>
