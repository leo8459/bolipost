<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"><title>Cierre diario</title></head>
<body style="font-family:Arial,sans-serif;color:#25344a">
<h1>Cierre diario de contratos y EMS</h1>
<p>Correos de Bolivia · Corte: {{ $report['cutoff'] }} (hora de Bolivia).</p>
<p>Actividad desde las 00:00 hasta el corte. Pendientes acumulados: todos los envíos que no están entregados ni cancelados, incluidas solicitudes aún sin recojo.</p>
@foreach ($report['modules'] as $module)
    <h2>{{ $module['name'] }}</h2>
    <p>Registrados hoy: <strong>{{ $module['registered'] }}</strong> · Entregados hoy: <strong>{{ $module['delivered'] }}</strong> · Pendientes: <strong>{{ $module['pending']->count() }}</strong> · Sin cartero activo: <strong>{{ $module['unassigned'] }}</strong></p>
    <h3>Movimientos del día</h3>
    <table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%">
        <tr><th>Evento</th><th>Movimientos</th><th>Envíos distintos</th></tr>
        @forelse ($module['activity'] as $event)
            <tr><td>{{ $event->nombre_evento ?? 'Evento '.$event->evento_id }}</td><td>{{ $event->movimientos }}</td><td>{{ $event->paquetes }}</td></tr>
        @empty
            <tr><td colspan="3">Sin movimientos registrados hoy.</td></tr>
        @endforelse
    </table>
    <h3>Pendientes por cartero</h3>
    <table cellpadding="8" cellspacing="0" border="1" style="border-collapse:collapse;width:100%">
        <tr><th>Cartero</th><th>Pendientes</th></tr>
        @forelse ($module['couriers'] as $courier)
            <tr><td>{{ $courier['name'] }}</td><td>{{ $courier['pending'] }}</td></tr>
        @empty
            <tr><td colspan="2">Sin pendientes asignados a carteros.</td></tr>
        @endforelse
    </table>
@endforeach
<p>El archivo Excel adjunto, dividido en hojas por departamento de destino, contiene todos los pendientes con código, destino, estado y cartero. Los movimientos cuentan eventos; un envío puede tener varios eventos durante el día.</p>
</body></html>
