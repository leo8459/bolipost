@php($numberClass = !empty($pdfMode) ? 'num' : 'text-right')
<table class="{{ !empty($pdfMode) ? 'report-table' : 'table report-table' }}">
    <thead>
        <tr><th>Regional de {{ $regionalType }}</th><th class="{{ $numberClass }}">Registros</th><th class="{{ $numberClass }}">Entregados</th><th class="{{ $numberClass }}">No entregados</th><th class="{{ $numberClass }}">Efectividad</th><th class="{{ $numberClass }}">Peso (kg)</th></tr>
    </thead>
    <tbody>
        @forelse($regionalRows as $regionalRow)
            <tr>
                <td>{{ $regionalRow['regional'] }}</td>
                <td class="{{ $numberClass }}">{{ \App\Support\BolivianNumber::format($regionalRow['registros']) }}</td>
                <td class="{{ $numberClass }}">{{ \App\Support\BolivianNumber::format($regionalRow['entregados']) }}</td>
                <td class="{{ $numberClass }}">{{ \App\Support\BolivianNumber::format($regionalRow['no_entregados']) }}</td>
                <td class="{{ $numberClass }}">{{ \App\Support\BolivianNumber::format($regionalRow['efectividad'], 1) }}%</td>
                <td class="{{ $numberClass }}">{{ \App\Support\BolivianNumber::format($regionalRow['peso'], 3) }}</td>
            </tr>
        @empty
            <tr><td colspan="6" class="empty-state">No hay registros para estas regionales en el periodo seleccionado. Prueba ampliar los meses o mostrar todas las regionales.</td></tr>
        @endforelse
    </tbody>
</table>
