<table>
    <thead>
        <tr>
            <th colspan="3">REPORTE DE BITACORAS POR DEPARTAMENTO</th>
        </tr>
        <tr>
            <th colspan="3">
                Generado: {{ $generatedAt->format('d/m/Y H:i') }}
                | Busqueda: {{ $filters['q'] !== '' ? $filters['q'] : 'Todas' }}
                | Regional: {{ $filters['regional'] !== '' ? $filters['regional'] : 'Todas' }}
                | Usuario: {{ ($filters['user'] ?? '') !== '' ? $filters['user'] : 'Todos' }}
                | Cod especial: {{ $filters['codEspecial'] !== '' ? $filters['codEspecial'] : 'Todos' }}
                | Provincia: {{ $filters['provincia'] !== '' ? $filters['provincia'] : 'Todas' }}
                | Origen CN-33: {{ $filters['origenCn33'] !== '' ? $filters['origenCn33'] : 'Todos' }}
            </th>
        </tr>
        <tr>
            <th colspan="3">RESUMEN GENERAL</th>
        </tr>
        <tr>
            <th>Metrica</th>
            <th>Valor</th>
            <th>Detalle</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>Precio total acumulado</td>
            <td>{{ number_format((float) data_get($reportTotals, 'total_precio', 0), 2, '.', '') }}</td>
            <td>Bs</td>
        </tr>
        <tr>
            <td>Registros agrupados</td>
            <td>{{ (int) data_get($reportTotals, 'total_registros', 0) }}</td>
            <td>Bitacoras finales</td>
        </tr>
        <tr>
            <td>Departamentos origen</td>
            <td>{{ (int) data_get($reportTotals, 'origenes', 0) }}</td>
            <td>Unicos</td>
        </tr>
        <tr>
            <td>Departamentos destino</td>
            <td>{{ (int) data_get($reportTotals, 'destinos', 0) }}</td>
            <td>Unicos</td>
        </tr>
        <tr>
            <td>Transportadoras</td>
            <td>{{ (int) data_get($reportTotals, 'transportadoras', 0) }}</td>
            <td>Unicas</td>
        </tr>
        <tr></tr>
        <tr>
            <th colspan="4">RANKING DE TRANSPORTADORAS</th>
        </tr>
        <tr>
            <th>Transportadora</th>
            <th>Envios</th>
            <th>Precio total</th>
            <th>Peso total</th>
        </tr>
        @forelse($reportByTransportadora as $row)
            <tr>
                <td>{{ strtoupper((string) $row->transportadora) }}</td>
                <td>{{ (int) $row->total_registros }}</td>
                <td>{{ number_format((float) $row->total_precio, 2, '.', '') }}</td>
                <td>{{ number_format((float) $row->total_peso, 3, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">No hay datos para mostrar.</td>
            </tr>
        @endforelse
        <tr></tr>
        <tr>
            <th colspan="3">TOTALES POR ORIGEN</th>
        </tr>
        <tr>
            <th>Origen</th>
            <th>Registros</th>
            <th>Precio total</th>
        </tr>
        @forelse($reportByOrigin as $row)
            <tr>
                <td>{{ $row->departamento }}</td>
                <td>{{ (int) $row->total_registros }}</td>
                <td>{{ number_format((float) $row->total_precio, 2, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">No hay datos para mostrar.</td>
            </tr>
        @endforelse
        <tr></tr>
        <tr>
            <th colspan="3">TOTALES POR DESTINO</th>
        </tr>
        <tr>
            <th>Destino</th>
            <th>Registros</th>
            <th>Precio total</th>
        </tr>
        @forelse($reportByDestination as $row)
            <tr>
                <td>{{ $row->departamento }}</td>
                <td>{{ (int) $row->total_registros }}</td>
                <td>{{ number_format((float) $row->total_precio, 2, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="3">No hay datos para mostrar.</td>
            </tr>
        @endforelse
        <tr></tr>
        <tr>
            <th colspan="5">CRUCE ORIGEN Y DESTINO</th>
        </tr>
        <tr>
            <th>Origen</th>
            <th>Destino</th>
            <th>Registros</th>
            <th>Precio total</th>
            <th>Peso total</th>
        </tr>
        @forelse($reportRows as $row)
            <tr>
                <td>{{ $row->origen_departamento }}</td>
                <td>{{ $row->destino_departamento }}</td>
                <td>{{ (int) $row->total_registros }}</td>
                <td>{{ number_format((float) $row->total_precio, 2, '.', '') }}</td>
                <td>{{ number_format((float) $row->total_peso, 3, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="5">No hay datos para mostrar.</td>
            </tr>
        @endforelse
    </tbody>
</table>
