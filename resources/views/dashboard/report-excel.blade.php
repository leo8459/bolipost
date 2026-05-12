<table>
    <tr>
        <td colspan="9"><strong>REPORTE DASHBOARD CORPORATIVO</strong></td>
    </tr>
    <tr>
        <td colspan="9">Rango: {{ $rangoLabel }}</td>
    </tr>
    <tr>
        <td colspan="9">Agrupacion: {{ strtoupper($agrupacion) }}</td>
    </tr>
    <tr>
        <td colspan="9">Departamento destino: {{ ($departamento ?? '') !== '' ? $departamento : 'TODOS' }}</td>
    </tr>
    <tr><td colspan="9"></td></tr>
</table>

<table>
    <thead>
        <tr>
            <th>Total registrados</th>
            <th>Total entregados</th>
            <th>Total pendientes</th>
            <th>Con retraso</th>
            <th>Rezago</th>
            <th>Peso total</th>
            <th>Ingresos (Bs)</th>
            <th>% Entrega</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $totales['paquetes'] }}</td>
            <td>{{ $totales['entregados'] }}</td>
            <td>{{ $totales['pendientes'] }}</td>
            <td>{{ $totales['atrasados'] }}</td>
            <td>{{ $totales['rezago'] }}</td>
            <td>{{ $totales['peso_total'] }}</td>
            <td>{{ $totales['ingresos'] }}</td>
            <td>{{ $totales['porcentaje_entrega'] }}%</td>
        </tr>
    </tbody>
</table>

<table>
    <tr><td colspan="9"></td></tr>
    <tr><td colspan="9"><strong>RESUMEN POR MODULO</strong></td></tr>
</table>
<table>
    <thead>
        <tr>
            <th>Modulo</th>
            <th>Registrados</th>
            <th>Entregados</th>
            <th>Pendientes</th>
            <th>Con retraso</th>
            <th>Rezago</th>
            <th>Tasa entrega</th>
            <th>Peso total</th>
            <th>Ingresos (Bs)</th>
        </tr>
    </thead>
    <tbody>
        @foreach($resumenPorModulo as $fila)
            <tr>
                <td>{{ $fila['label'] }}</td>
                <td>{{ $fila['total'] }}</td>
                <td>{{ $fila['entregados'] }}</td>
                <td>{{ $fila['pendientes'] }}</td>
                <td>{{ $fila['atrasados'] }}</td>
                <td>{{ $fila['rezago'] }}</td>
                <td>{{ $fila['tasa_entrega'] }}%</td>
                <td>{{ $fila['peso_total'] }}</td>
                <td>{{ $fila['ingresos'] }}</td>
            </tr>
        @endforeach
    </tbody>
</table>

<table>
    <tr><td colspan="7"></td></tr>
    <tr><td colspan="7"><strong>RANKING DEPARTAMENTOS: ENTREGADOS VS PENDIENTES</strong></td></tr>
    @if(($rankingDepartamentos ?? collect())->isNotEmpty())
        @php($topDepartamento = ($rankingDepartamentos ?? collect())->first())
        <tr>
            <td colspan="7">
                #1 {{ $topDepartamento->departamento }} - {{ $topDepartamento->cumplimiento }}% cumplimiento.
                Mejor entregador: {{ $topDepartamento->top_entregador }} ({{ $topDepartamento->top_entregador_total }} entregas)
            </td>
        </tr>
    @endif
    <tr>
        <th>#</th>
        <th>Departamento</th>
        <th>Registrados</th>
        <th>Entregados</th>
        <th>Pendientes</th>
        <th>Cumplimiento</th>
        <th>Quien entrega mas</th>
    </tr>
    @forelse(($rankingDepartamentos ?? collect()) as $item)
        <tr>
            <td>{{ $item->puesto }}</td>
            <td>{{ $item->departamento }}</td>
            <td>{{ $item->total }}</td>
            <td>{{ $item->entregados }}</td>
            <td>{{ $item->pendientes }}</td>
            <td>{{ $item->cumplimiento }}%</td>
            <td>{{ $item->top_entregador }} ({{ $item->top_entregador_total }})</td>
        </tr>
    @empty
        <tr><td colspan="7">Sin datos por departamento</td></tr>
    @endforelse
</table>

<table>
    <tr><td colspan="4"></td></tr>
    <tr><td colspan="4"><strong>KPIs DE REGISTRO</strong></td></tr>
    <tr><th>Hoy</th><th>Semana</th><th>Mes</th><th></th></tr>
    <tr>
        <td>{{ $kpisPeriodo['registros']['dia'] }}</td>
        <td>{{ $kpisPeriodo['registros']['semana'] }}</td>
        <td>{{ $kpisPeriodo['registros']['mes'] }}</td>
        <td></td>
    </tr>
    <tr><td colspan="4"></td></tr>
    <tr><td colspan="4"><strong>KPIs DE ENTREGA</strong></td></tr>
    <tr><th>Hoy</th><th>Semana</th><th>Mes</th><th></th></tr>
    <tr>
        <td>{{ $kpisPeriodo['entregas']['dia'] }}</td>
        <td>{{ $kpisPeriodo['entregas']['semana'] }}</td>
        <td>{{ $kpisPeriodo['entregas']['mes'] }}</td>
        <td></td>
    </tr>
</table>

<table>
    <tr><td colspan="3"></td></tr>
    <tr><td colspan="3"><strong>TENDENCIA ({{ $rangoTendenciaLabel }})</strong></td></tr>
    <tr>
        <th>Periodo</th>
        <th>Registros</th>
        <th>Entregados</th>
    </tr>
    @foreach($trendLabels as $i => $label)
        <tr>
            <td>{{ $label }}</td>
            <td>{{ $trendSeries['registros'][$i] ?? 0 }}</td>
            <td>{{ $trendSeries['entregados'][$i] ?? 0 }}</td>
        </tr>
    @endforeach
</table>

<table>
    <tr><td colspan="3"></td></tr>
    <tr><td colspan="3"><strong>TOP ENTREGADORES</strong></td></tr>
    <tr><th>Usuario</th><th>Total</th><th>Detalle</th></tr>
    @forelse($rankingEntregadores as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>{{ (int) $item->total_entregados }}</td>
            <td>E:{{ (int) $item->ems }} C:{{ (int) $item->contrato }} Ce:{{ (int) $item->certi }} O:{{ (int) $item->ordi }}</td>
        </tr>
    @empty
        <tr><td colspan="3">Sin datos</td></tr>
    @endforelse
</table>

<table>
    <tr><td colspan="3"></td></tr>
    <tr><td colspan="3"><strong>TOP REGISTRADORES</strong></td></tr>
    <tr><th>Usuario</th><th>Total</th><th>Detalle</th></tr>
    @forelse($rankingRegistradores as $item)
        <tr>
            <td>{{ $item->name }}</td>
            <td>{{ (int) $item->total_registrados }}</td>
            <td>E:{{ (int) $item->ems }} C:{{ (int) $item->contrato }} Ce:{{ (int) $item->certi }} O:{{ (int) $item->ordi }}</td>
        </tr>
    @empty
        <tr><td colspan="3">Sin datos</td></tr>
    @endforelse
</table>

