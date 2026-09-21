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
<p>El archivo Excel adjunto contiene el detalle completo de los movimientos de ese día, organizado por departamento de destino.</p>
</body></html>
