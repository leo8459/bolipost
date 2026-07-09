<table>
    <tr>
        <td colspan="{{ 5 + count($eventColumns) }}"><strong>REPORTE PERFORMANCE</strong></td>
    </tr>
    <tr>
        <td colspan="{{ 5 + count($eventColumns) }}">Generado: {{ $generatedAt->format('d/m/Y H:i') }}</td>
    </tr>
    @foreach($filterSummary as $label => $value)
        <tr>
            <td><strong>{{ $label }}</strong></td>
            <td colspan="{{ 4 + count($eventColumns) }}">{{ $value }}</td>
        </tr>
    @endforeach

    <tr></tr>
    <tr>
        <td><strong>Origenes</strong></td>
        <td>{{ $summary['origenes'] }}</td>
        <td><strong>Destinos</strong></td>
        <td>{{ $summary['destinos'] }}</td>
        <td><strong>Registros</strong></td>
        <td>{{ $summary['total_registros'] }}</td>
    </tr>

    <tr></tr>
    <tr>
        <th>Origen</th>
        <th>Destino</th>
        <th>Año</th>
        <th>Mes</th>
        @foreach($eventColumns as $eventColumn)
            <th>{{ $eventColumn }}</th>
        @endforeach
        <th>Total</th>
    </tr>
    @foreach($matrixRows as $matrixRow)
        <tr>
            <td>{{ $matrixRow['origen'] }}</td>
            <td>{{ $matrixRow['destino'] }}</td>
            <td>{{ $matrixRow['anio'] }}</td>
            <td>{{ $matrixRow['mes_label'] }}</td>
            @foreach($eventColumns as $eventColumn)
                <td>{{ (int) ($matrixRow['counts'][$eventColumn] ?? 0) }}</td>
            @endforeach
            <td>{{ (int) $matrixRow['total'] }}</td>
        </tr>
    @endforeach
    @if(count($matrixRows) > 0)
        <tr>
            <th colspan="4">Totales</th>
            @foreach($eventColumns as $eventColumn)
                <th>{{ (int) ($matrixTotals['events'][$eventColumn] ?? 0) }}</th>
            @endforeach
            <th>{{ (int) ($matrixTotals['grand_total'] ?? 0) }}</th>
        </tr>
    @endif

    <tr></tr>
    <tr>
        <th>Fecha</th>
        <th>Servicio</th>
        <th>Codigo</th>
        <th>Evento</th>
        <th>Origen</th>
        <th>Destino</th>
        <th>Actor</th>
    </tr>
    @foreach($details as $detail)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::parse($detail->created_at)->format('d/m/Y H:i') }}</td>
            <td>{{ $detail->servicio }}</td>
            <td>{{ $detail->codigo }}</td>
            <td>{{ $detail->evento_nombre }}</td>
            <td>{{ $detail->origen }}</td>
            <td>{{ $detail->destino }}</td>
            <td>{{ $detail->actor_nombre }}</td>
        </tr>
    @endforeach
</table>
