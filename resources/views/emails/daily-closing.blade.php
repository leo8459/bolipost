<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Cierre diario</title></head>
<body style="font-family:Arial,sans-serif;color:#25344a">
<h1>Cierre diario de contratos y EMS</h1>
<p>Correos de Bolivia · Día: {{ \Carbon\CarbonImmutable::parse($report['date'])->format('d/m/Y') }}.</p>
<p>Este cierre contiene únicamente los movimientos registrados en la fecha indicada.</p>
@foreach ($report['modules'] as $module)
    <h2>{{ $module['name'] }}</h2>
    <p>
        Registrados: <strong>{{ $module['registered'] }}</strong> ·
        Entregados: <strong>{{ $module['delivered'] }}</strong> ·
        Movimientos: <strong>{{ $module['movements']->count() }}</strong> ·
        Envíos con movimiento: <strong>{{ $module['moved_packages'] }}</strong>
    </p>
    <h3>Movimientos por evento</h3>
    <table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%">
        <tr><th>Evento</th><th>Movimientos</th><th>Envíos distintos</th></tr>
        @forelse ($module['activity'] as $event)
            <tr><td>{{ $event->nombre_evento ?? 'Evento '.$event->evento_id }}</td><td>{{ $event->movimientos }}</td><td>{{ $event->paquetes }}</td></tr>
        @empty
            <tr><td colspan="3">Sin movimientos registrados en la fecha elegida.</td></tr>
        @endforelse
    </table>
@endforeach
<p>El archivo Excel adjunto contiene el detalle completo de los movimientos de ese día, organizado por departamento de origen.</p>
<h2>Detalle de movimientos por departamento</h2>
@php
    $departmentMovements = collect($report['modules'])
        ->flatMap(fn ($module) => $module['daily_packages']->map(fn ($package) => [
            'service' => $module['name'],
            'package' => $package,
        ]))
        ->groupBy(function ($item) {
            $origin = strtoupper(trim(\Illuminate\Support\Str::ascii((string) $item['package']->origen)));
            $origin = preg_replace('/\\s+/', ' ', $origin);
            return match ($origin) {
                'SUCRE' => 'CHUQUISACA',
                'TRINIDAD' => 'BENI',
                'COBIJA' => 'PANDO',
                'EL ALTO' => 'LA PAZ',
                'LA PAZ', 'COCHABAMBA', 'SANTA CRUZ', 'ORURO', 'POTOSI', 'CHUQUISACA', 'TARIJA', 'BENI', 'PANDO' => $origin,
                default => 'SIN DEPARTAMENTO',
            };
        })
        ->sortKeys();
@endphp
@forelse ($departmentMovements as $department => $items)
    <h3>{{ $department }}</h3>
    <table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%;margin-bottom:18px">
        <tr><th>Servicio</th><th>Código</th><th>Origen</th><th>Destino</th><th>Eventos del día (hora · evento · usuario)</th><th>Estado actual</th><th>Cartero actual</th></tr>
        @foreach ($items as $item)
            @php($package = $item['package'])
            <tr>
                <td>{{ $item['service'] }}</td>
                <td>{{ $package->codigo }}</td>
                <td>{{ $package->origen ?: 'Sin origen' }}</td>
                <td>{{ $package->destino ?: 'Sin destino' }}</td>
                <td>{!! nl2br(e($package->timeline)) !!}</td>
                <td>{{ $package->estado ?: 'Sin estado' }}</td>
                <td>{{ $package->cartero ?: 'Sin cartero' }}</td>
            </tr>
        @endforeach
    </table>
@empty
    <p>No hubo movimientos de contratos ni EMS en la fecha indicada.</p>
@endforelse
</body></html>
