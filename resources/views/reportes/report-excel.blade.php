<table>
    <tr>
        <td colspan="15"><strong>{{ strtoupper($scopeLabel) }}</strong></td>
    </tr>
    <tr>
        <td colspan="15">Generado: {{ now()->format('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="15">Modulos: {{ implode(', ', $moduleLabels) }}</td>
    </tr>
    <tr><td colspan="15"></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>Registrados</th>
            <th>Total filtrado</th>
            <th>Entregados</th>
            <th>No entregados</th>
            <th>Peso total</th>
            <th>Ingresos (Bs)</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $summary['registrados'] ?? ($summary['total'] ?? 0) }}</td>
            <td>{{ $summary['total_filtrado'] ?? ($summary['total'] ?? 0) }}</td>
            <td>{{ $summary['entregados'] ?? 0 }}</td>
            <td>{{ $summary['no_entregados'] ?? 0 }}</td>
            <td>{{ number_format((float) ($totals['peso_total'] ?? 0), 3, '.', '') }}</td>
            <td>{{ number_format((float) ($totals['precio_total'] ?? 0), 2, '.', '') }}</td>
        </tr>
    </tbody>
</table>

<table>
    <tr><td colspan="15"></td></tr>
    <tr><td colspan="15"><strong>RESUMEN POR MODULO</strong></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>Modulo</th>
            <th>Total</th>
            <th>Entregados</th>
            <th>Pendientes</th>
            <th>Peso</th>
            <th>Ingresos</th>
        </tr>
    </thead>
    <tbody>
        @forelse($moduleSummary as $mod)
            <tr>
                <td>{{ $mod['label'] }}</td>
                <td>{{ $mod['total'] }}</td>
                <td>{{ $mod['entregados'] }}</td>
                <td>{{ $mod['no_entregados'] }}</td>
                <td>{{ number_format((float) $mod['peso'], 3, '.', '') }}</td>
                <td>{{ number_format((float) $mod['precio'], 2, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="6">Sin datos por modulo.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table>
    <tr><td colspan="15"></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Modulo</th>
            <th>Codigo</th>
            <th>Estado</th>
            <th>Situacion</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Remitente</th>
            <th>Destinatario</th>
            <th>Empresa</th>
            <th>Usuario</th>
            <th>Peso</th>
            <th>Precio</th>
            <th>Creado</th>
            <th>Actualizado</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $idx => $row)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $row['modulo_label'] }}</td>
                <td>{{ $row['codigo'] }}</td>
                <td>{{ $row['estado'] }}</td>
                <td>{{ $row['situacion'] }}</td>
                <td>{{ $row['origen'] }}</td>
                <td>{{ $row['destino'] }}</td>
                <td>{{ $row['remitente'] }}</td>
                <td>{{ $row['destinatario'] }}</td>
                <td>{{ $row['empresa'] }}</td>
                <td>{{ $row['usuario'] }}</td>
                <td>{{ number_format((float) $row['peso'], 3, '.', '') }}</td>
                <td>{{ number_format((float) $row['precio'], 2, '.', '') }}</td>
                <td>{{ $row['created_at'] }}</td>
                <td>{{ $row['updated_at'] }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="15">Sin resultados para los filtros seleccionados.</td>
            </tr>
        @endforelse
        <tr>
            <td colspan="11"><strong>Totales</strong></td>
            <td><strong>{{ number_format((float) ($totals['peso_total'] ?? 0), 3, '.', '') }}</strong></td>
            <td><strong>{{ number_format((float) ($totals['precio_total'] ?? 0), 2, '.', '') }}</strong></td>
            <td colspan="2"></td>
        </tr>
    </tbody>
</table>
