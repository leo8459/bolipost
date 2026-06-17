<table>
    <tr>
        <td colspan="22"><strong>{{ strtoupper($scopeLabel) }}</strong></td>
    </tr>
    <tr>
        <td colspan="22">Generado: {{ now()->format('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="22">Módulos: {{ implode(', ', $moduleLabels) }}</td>
    </tr>
    <tr>
        <td colspan="22">
            Este archivo contiene todos los registros creados que cumplen los filtros, excluyendo cancelados.
            Use los filtros de la fila de encabezados para revisar por módulo, servicio, estado, origen o destino.
        </td>
    </tr>
    @if(!empty($selectedMonthLabels))
        <tr>
            <td colspan="22">Meses seleccionados: {{ implode(', ', $selectedMonthLabels) }}</td>
        </tr>
    @elseif(!empty($from) || !empty($to))
        <tr>
            <td colspan="22">Rango de fechas: {{ $from ?: 'inicio' }} - {{ $to ?: 'fin' }}</td>
        </tr>
    @else
        <tr>
            <td colspan="22">Rango de fechas: todos</td>
        </tr>
    @endif
    <tr><td colspan="22"></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>Registrados</th>
            <th>Mostrados</th>
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
    <tr><td colspan="22"></td></tr>
    <tr><td colspan="22"><strong>RESUMEN POR MÓDULO</strong></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>Módulo</th>
            <th>Total</th>
            <th>Entregados</th>
            <th>No entregados</th>
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
                <td colspan="6">Sin datos por módulo.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table>
    <tr><td colspan="22"></td></tr>
    <tr><td colspan="22"><strong>RESUMEN POR SERVICIO</strong></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>Servicio</th>
            <th>Cantidad</th>
            <th>Peso</th>
            <th>Ingresos</th>
        </tr>
    </thead>
    <tbody>
        @forelse(($serviceSummary ?? []) as $serviceRow)
            <tr>
                <td>{{ $serviceRow['servicio'] }}</td>
                <td>{{ $serviceRow['cantidad'] }}</td>
                <td>{{ number_format((float) $serviceRow['peso'], 3, '.', '') }}</td>
                <td>{{ number_format((float) $serviceRow['precio'], 2, '.', '') }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="4">Sin datos por servicio.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table>
    <tr><td colspan="22"></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Modulo</th>
            <th>Servicio</th>
            <th>Codigo guia</th>
            <th>Estado actual</th>
            <th>Situacion</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Remitente</th>
            <th>Destinatario</th>
            <th>Empresa</th>
            <th>Usuario registro</th>
            <th>Roles usuario</th>
            <th>Regional</th>
            <th>Entregado por</th>
            <th>Roles entrega</th>
            <th>Peso</th>
            <th>Ingreso Bs</th>
            <th>Fecha registro</th>
            <th>Ult. actualizacion</th>
            <th>Fecha entrega</th>
            <th>Tiempo entrega hrs</th>
        </tr>
    </thead>
    <tbody>
        @forelse($rows as $idx => $row)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td>{{ $row['modulo_label'] }}</td>
                <td>{{ $row['servicio'] }}</td>
                <td>{{ $row['codigo'] }}</td>
                <td>{{ $row['estado'] }}</td>
                <td>{{ $row['situacion'] }}</td>
                <td>{{ $row['origen'] }}</td>
                <td>{{ $row['destino'] }}</td>
                <td>{{ $row['remitente'] }}</td>
                <td>{{ $row['destinatario'] }}</td>
                <td>{{ $row['empresa'] }}</td>
                <td>{{ $row['usuario'] }}</td>
                <td>{{ $row['usuario_roles'] }}</td>
                <td>{{ $row['regional'] }}</td>
                <td>{{ $row['entregado_por'] }}</td>
                <td>{{ $row['entregado_por_roles'] }}</td>
                <td>{{ (float) $row['peso'] }}</td>
                <td>{{ (float) $row['precio'] }}</td>
                <td>{{ $row['created_at'] }}</td>
                <td>{{ $row['updated_at'] }}</td>
                <td>{{ $row['delivered_at'] }}</td>
                <td>{{ $row['delivery_hours'] ?? '-' }}</td>
            </tr>
        @empty
            <tr>
                <td colspan="22">Sin resultados para los filtros seleccionados.</td>
            </tr>
        @endforelse
    </tbody>
</table>

<table>
    <tr><td colspan="22"></td></tr>
    <tr>
        <td colspan="16"><strong>Totales</strong></td>
        <td><strong>{{ number_format((float) ($totals['peso_total'] ?? 0), 3, '.', '') }}</strong></td>
        <td><strong>{{ number_format((float) ($totals['precio_total'] ?? 0), 2, '.', '') }}</strong></td>
        <td colspan="4"></td>
    </tr>
</table>
