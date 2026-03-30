@php
    $columnLabels = [
        'fecha' => 'Fecha',
        'placa' => 'Placa',
        'vehiculo' => 'Vehiculo',
        'driver_name' => 'Conductor',
        'kilometraje_salida' => 'Km salida',
        'kilometraje_recorrido' => 'Km recorrido',
        'kilometraje_llegada' => 'Km llegada',
        'recorrido' => 'Recorrido',
        'combustible' => 'Combustible',
    ];
    $visibleColumns = collect($visibleColumns ?? array_keys($columnLabels))
        ->filter(fn ($column) => array_key_exists($column, $columnLabels))
        ->values();
    $columnCount = max($visibleColumns->count(), 1);
@endphp
<table>
    <thead>
        <tr>
            <th colspan="{{ $columnCount }}">BITACORA VEHICULAR</th>
        </tr>
        <tr>
            <th colspan="{{ $columnCount }}">
                Generado: {{ now()->format('d/m/Y H:i') }}
                | Desde: {{ $fechaDesde ?: 'Todos' }}
                | Hasta: {{ $fechaHasta ?: 'Todos' }}
                | Busqueda: {{ $placaFiltro !== '' ? $placaFiltro : 'Todas' }}
                | Vehiculo: {{ $selectedVehicleLabel !== '' ? $selectedVehicleLabel : 'Todos' }}
                | Conductor: {{ $selectedDriverLabel !== '' ? $selectedDriverLabel : 'Todos' }}
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
                    @if(in_array($column, ['kilometraje_salida', 'kilometraje_recorrido', 'kilometraje_llegada'], true))
                        <td>{{ $row[$column] !== null ? number_format((float) $row[$column], 2, '.', '') : '' }}</td>
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
    </tbody>
</table>
