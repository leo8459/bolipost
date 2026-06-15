@php
    $totalCantidad = 0;
    $totalPeso = 0;
@endphp

<table>
    <tr>
        <td colspan="10">CORREOS DE BOLIVIA - MANIFIESTO CN-33</td>
    </tr>
    <tr>
        <td colspan="10">DESPACHO: {{ $currentManifiesto }}</td>
    </tr>
    <tr>
        <td colspan="2">Oficina origen: {{ $loggedInUserCity }}</td>
        <td colspan="2">Oficina destino: {{ $destinationCity }}</td>
        <td colspan="2">Fecha: {{ $generatedAt->format('d/m/Y') }}</td>
        <td colspan="4">Hora: {{ $generatedAt->format('H:i') }}</td>
    </tr>
    <tr>
        <td colspan="2">Filtro origen: {{ $filterOrigin !== '' ? $filterOrigin : 'TODOS' }}</td>
        <td colspan="2">Filtro destino: {{ $filterDestination !== '' ? $filterDestination : 'TODOS' }}</td>
        <td colspan="2">Modo: {{ $selectedTransport }}</td>
        <td colspan="4">Transporte: {{ $numeroVuelo !== '' ? $numeroVuelo : '-' }}</td>
    </tr>
    <tr>
        <td colspan="10">LISTA DE MANIFIESTO</td>
    </tr>
    <tr>
        <th>TIPO</th>
        <th>ENVIO</th>
        <th>ORIG.</th>
        <th>DEST.</th>
        <th>CANT.</th>
        <th>COR</th>
        <th>PESO</th>
        <th>REMITENTE</th>
        <th>EMS</th>
        <th>OBSERVACION</th>
    </tr>
    @foreach ($paquetes as $paquete)
        @php
            $cantidad = (int) ($paquete->cantidad ?? 1);
            $peso = (float) ($paquete->peso ?? 0);
            $totalCantidad += $cantidad;
            $totalPeso += $peso;
        @endphp
        <tr>
            <td>{{ $paquete->tipo ?? '-' }}</td>
            <td>{{ $paquete->codigo ?? '-' }}</td>
            <td>{{ $paquete->origen ?? '-' }}</td>
            <td>{{ $paquete->destino ?? $destinationCity }}</td>
            <td>{{ $cantidad }}</td>
            <td></td>
            <td>{{ number_format($peso, 3) }}</td>
            <td>{{ $paquete->nombre_remitente ?? '-' }}</td>
            <td>X</td>
            <td>{{ $paquete->observacion ?? '' }}</td>
        </tr>
    @endforeach
    <tr>
        <td colspan="4"><strong>TOTAL</strong></td>
        <td><strong>{{ $totalCantidad }}</strong></td>
        <td></td>
        <td><strong>{{ number_format($totalPeso, 3) }} Kg</strong></td>
        <td colspan="3"></td>
    </tr>
</table>
