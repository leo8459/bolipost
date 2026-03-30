@php
    $columnLabels = [
        'station_name' => 'Estacion de servicio',
        'invoice_number' => 'Numero de factura',
        'regional' => 'Regional',
        'fecha_carga' => 'Fecha de carga',
        'litros' => 'Litros',
        'importe_bs' => 'Importe Bs.',
        'total_km' => 'Total km recorrido',
        'placa' => 'N de placa',
        'vehiculo' => 'Vehiculo',
        'driver_name' => 'Nombre del conductor',
    ];
    $visibleColumns = collect($visibleColumns ?? array_keys($columnLabels))
        ->filter(fn ($column) => array_key_exists($column, $columnLabels))
        ->values();
    $columnCount = max($visibleColumns->count(), 1);
@endphp
<table>
    <thead>
        <tr>
            <th colspan="{{ $columnCount }}">PLANILLA DE USO DE COMBUSTIBLES (GASOLINA)</th>
        </tr>
        <tr>
            <th colspan="{{ $columnCount }}">
                Generado: {{ now()->format('d/m/Y H:i') }}
                | Desde: {{ $fechaDesde ?: 'Todos' }}
                | Hasta: {{ $fechaHasta ?: 'Todos' }}
                | Placa filtro: {{ $placaFiltro !== '' ? $placaFiltro : 'Todas' }}
                | Vehiculo: {{ ($selectedVehicleLabel ?? '') !== '' ? $selectedVehicleLabel : 'Todos' }}
                | Conductor: {{ ($selectedDriverLabel ?? '') !== '' ? $selectedDriverLabel : 'Todos' }}
            </th>
        </tr>
        <tr>
            @foreach($visibleColumns as $column)
                <th>{{ $columnLabels[$column] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $row)
            <tr>
                @foreach($visibleColumns as $column)
                    @if($column === 'litros')
                        <td>{{ ($row['litros'] ?? 0) > 0 ? number_format((float) $row['litros'], 3, '.', '') : '' }}</td>
                    @elseif($column === 'importe_bs')
                        <td>{{ ($row['importe_bs'] ?? 0) > 0 ? number_format((float) $row['importe_bs'], 2, '.', '') : '' }}</td>
                    @elseif($column === 'total_km')
                        <td>{{ ($row['total_km'] ?? null) !== null ? number_format((float) $row['total_km'], 3, '.', '') : '' }}</td>
                    @else
                        <td>{{ $row[$column] ?: '-' }}</td>
                    @endif
                @endforeach
            </tr>
        @empty
            <tr>
                <td colspan="{{ $columnCount }}">No existen registros para los filtros seleccionados.</td>
            </tr>
        @endforelse
        <tr>
            @foreach($visibleColumns as $index => $column)
                @if($index === 0)
                    <td><strong>TOTALES</strong></td>
                @elseif($column === 'litros')
                    <td><strong>{{ number_format((float) ($totals['litros'] ?? 0), 3, '.', '') }}</strong></td>
                @elseif($column === 'importe_bs')
                    <td><strong>{{ number_format((float) ($totals['importe_bs'] ?? 0), 2, '.', '') }}</strong></td>
                @elseif($column === 'total_km')
                    <td><strong>{{ number_format((float) ($totals['total_km'] ?? 0), 3, '.', '') }}</strong></td>
                @else
                    <td></td>
                @endif
            @endforeach
        </tr>
    </tbody>
</table>
