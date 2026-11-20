<p class="delivery-report-cutoff">Operaciones del día hasta el {{ $report['cutoff'] }}, hora de Bolivia.</p>

<h3>Comparativo por servicio</h3>
<div class="table-responsive">
    <table class="delivery-report-summary" cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;width:100%;border-color:#d5dfed">
        <thead style="background:#eef4fb">
            <tr><th scope="col">Servicio</th><th scope="col">Registrados</th><th scope="col">Asignados</th><th scope="col">Entregados</th></tr>
        </thead>
        <tbody>
            @foreach ($report['modules'] as $module)
                <tr>
                    <th scope="row">{{ $module['name'] }}</th>
                    <td align="right">{{ \App\Support\BolivianNumber::format($module['registered']) }}</td>
                    <td align="right">{{ \App\Support\BolivianNumber::format($module['assigned']) }}</td>
                    <td align="right">{{ \App\Support\BolivianNumber::format($module['delivered']) }}</td>
                </tr>
            @endforeach
        </tbody>
        <tfoot style="background:#eef4fb">
            <tr>
                <th scope="row">Total</th>
                <td align="right"><strong>{{ \App\Support\BolivianNumber::format($report['totals']['registered']) }}</strong></td>
                <td align="right"><strong>{{ \App\Support\BolivianNumber::format($report['totals']['assigned']) }}</strong></td>
                <td align="right"><strong>{{ \App\Support\BolivianNumber::format($report['totals']['delivered']) }}</strong></td>
            </tr>
        </tfoot>
    </table>
</div>
<p class="delivery-report-note">Los totales excluyen los envíos cancelados y cuentan cada código una vez por servicio. Los registros, asignaciones y entregas se cuentan según la fecha de cada operación.</p>

<h3>Asignaciones y entregas por cartero</h3>
<div class="table-responsive">
    <table class="delivery-report-couriers" cellpadding="6" cellspacing="0" border="1" style="border-collapse:collapse;width:100%;border-color:#d5dfed">
        <thead style="background:#eef4fb">
            <tr>
                <th class="delivery-courier-name" scope="col" rowspan="2">Cartero o usuario</th>
                <th class="delivery-courier-department" scope="col" rowspan="2">Departamento</th>
                <th scope="colgroup" colspan="2">EMS</th>
                <th scope="colgroup" colspan="2">Contratos</th>
                <th scope="colgroup" colspan="2">Total</th>
            </tr>
            <tr>
                @foreach ([1, 2, 3] as $group)
                    <th scope="col">Asignados</th><th scope="col">Entregados</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['couriers'] as $courier)
                <tr>
                    <th scope="row">{{ $courier['name'] }}</th>
                    <td>{{ $courier['department'] }}</td>
                    @foreach ($courier['modules'] as $counts)
                        <td align="right">{{ \App\Support\BolivianNumber::format($counts['assigned']) }}</td>
                        <td align="right">{{ \App\Support\BolivianNumber::format($counts['delivered']) }}</td>
                    @endforeach
                    <td align="right">{{ \App\Support\BolivianNumber::format($courier['assigned']) }}</td>
                    <td align="right">{{ \App\Support\BolivianNumber::format($courier['delivered']) }}</td>
                </tr>
            @empty
                <tr><td colspan="8">No hay carteros ni usuarios con actividad en esta fecha.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<p class="delivery-report-note">Un envío reasignado puede aparecer en más de una fila. Las entregas se atribuyen al usuario que registró la entrega.</p>
