@extends('adminlte::page')
@section('title', $solicitud->numero)
@section('content_header')
<div class="d-flex justify-content-between flex-wrap no-print"><a href="{{ route('solicitudes-cambio.index') }}" class="btn btn-outline-secondary">Volver a solicitudes</a><button type="button" class="btn btn-primary" onclick="window.print()"><i class="fas fa-print mr-1" aria-hidden="true"></i> Imprimir ficha</button></div>
@stop
@section('content')
@if(session('success'))<div class="alert alert-success no-print" role="status">{{ session('success') }}</div>@endif
<article class="card ficha">
    <div class="membrete no-print"><img src="{{ asset('images/encabezado_contratos.jpeg') }}" alt="Ministerio de Obras Públicas, Servicios y Vivienda y Correos de Bolivia"></div>
    <div class="membrete membrete-print"><img src="{{ asset('images/encabezado_contratos.jpeg') }}" alt=""></div>
    <div class="pie-institucional" aria-label="Correos de Bolivia. Zona Central, avenida Mariscal Santa Cruz, esquina calle Oruro, Edificio Centro de Comunicaciones. Teléfono +591 2 2152423">
        <strong>CORREOS DE BOLIVIA</strong><br>
        Zona Central - Av. Mariscal Santa Cruz, Esq. Calle Oruro, Edif. Centro de Comunicaciones<br>
        Teléfonos: +591 (2) 2152423
        <span class="borde-pie" aria-hidden="true"></span>
        <span class="tricolor" aria-hidden="true"><i></i><i></i><i></i></span>
    </div>
    <div class="card-body">
    <h1 class="h4 text-center font-weight-bold">INFORME DE REQUERIMIENTO DE SISTEMAS</h1>
    <p class="text-center text-muted">Solicitud formal para la evaluación y atención de una necesidad relacionada con los sistemas institucionales.</p>
    <h2 class="h5 mt-4">Datos del informe</h2>
    <table class="table table-bordered"><tbody>
        <tr><th>N.º de requerimiento</th><td>{{ $solicitud->numero }}</td><th>Fecha</th><td>{{ $solicitud->ciudad }}, {{ $solicitud->fecha->format('d/m/Y') }}</td></tr>
        <tr><th>A</th><td colspan="3">ÁREA DE SISTEMAS</td></tr>
        <tr><th>De</th><td colspan="3">{{ $solicitud->solicitante }}</td></tr>
        <tr><th>Cargo</th><td>{{ $solicitud->cargo }}</td><th>Unidad o área</th><td>{{ $solicitud->unidad }}</td></tr>
    </tbody></table>
    <h2 class="h5 mt-4">Identificación del requerimiento</h2>
    <table class="table table-bordered"><tbody>@foreach (['sistema' => 'Sistema', 'modulo' => 'Módulo o servicio', 'ambiente' => 'Ambiente', 'tipo' => 'Tipo de requerimiento'] as $field => $label)<tr><th>{{ $label }}</th><td>{{ $solicitud->$field }}@if($field === 'tipo' && $solicitud->tipo === 'Otro'): {{ $solicitud->otro_tipo }}@endif</td></tr>@endforeach</tbody></table>
    <h2 class="h5 mt-4">Antecedentes o situación actual</h2><div class="border rounded p-3 texto">{{ $solicitud->antecedentes }}</div>
    <h2 class="h5 mt-4">Requerimiento solicitado</h2><div class="border rounded p-3 texto">{{ $solicitud->detalle }}</div>
    <h2 class="h5 mt-4">Prioridad de atención: {{ $solicitud->prioridad }}</h2>
    <p class="mt-4">Por lo expuesto, solicito la evaluación y atención del requerimiento descrito.</p>
    <div class="firma text-center"><p>Firma del solicitante</p><div class="linea-firma"></div><p>{{ $solicitud->solicitante }}<br>Firma y sello</p></div>
</div></article>
@stop
@section('css')
<style>
.ficha { max-width: 1000px; margin: 0 auto 2rem; position: relative; overflow: hidden; background: #fff; } .membrete { width: 100%; line-height: 0; } .membrete img { width: 100%; height: auto; } .membrete-print { display: none; } .texto { white-space: pre-wrap; overflow-wrap: anywhere; } .ficha td { overflow-wrap: anywhere; } .ficha table { table-layout: fixed; } .firma { margin-top: 3rem; break-inside: avoid; } .linea-firma { border-bottom: 1px solid #333; max-width: 320px; margin: 4rem auto 1rem; }
.ficha .card-body { position: relative; z-index: 1; padding-bottom: 96px; }
.pie-institucional { position: absolute; z-index: 2; right: 15px; bottom: 0; left: 15px; min-height: 76px; padding: 0 15px 26px 0; color: #454545; font-size: .82rem; font-weight: 600; line-height: 1.3; text-align: right; }
.borde-pie { position: absolute; right: 0; top: 0; bottom: 25px; width: 4px; background: #c9a746; }
.tricolor { position: absolute; left: 15px; bottom: 7px; display: flex; width: 36%; height: 7px; }
.tricolor i { flex: 1; } .tricolor i:nth-child(1) { background: #e83b35; } .tricolor i:nth-child(2) { background: #f2df35; } .tricolor i:nth-child(3) { background: #168947; }
@media(max-width: 576px) { .ficha .table td, .ficha .table th { padding: .4rem; font-size: .85rem; } .pie-institucional { font-size: .68rem; } }
@media print { @page { size: A4; margin: 14mm 16mm 25mm; } html, body, .content-wrapper { background: #fff !important; } body { font-size: 11pt; -webkit-print-color-adjust: exact; print-color-adjust: exact; } .main-header, .main-sidebar, .main-footer, .no-print, .content-header { display: none !important; } .content-wrapper { margin: 0 !important; } .content { padding: 0 !important; } .ficha { position: static; overflow: visible; box-shadow: none !important; border: 0; max-width: none; margin: 0; background: transparent; } .membrete-print { display: block; position: fixed; z-index: 5; top: 4mm; left: -16mm; width: 210mm; } .ficha .card-body { padding: 19mm 0 25mm; } .pie-institucional { position: fixed; z-index: 5; right: -15mm; bottom: -19mm; left: auto; width: 175mm; min-height: 29mm; padding: 0 7mm 9mm 0; background: #fff; font-size: 9pt; } .borde-pie { bottom: 9mm; width: 1mm; } .tricolor { left: 0; bottom: 3mm; height: 2mm; } .ficha h2 { break-after: avoid; } .ficha tr { break-inside: avoid; } }
</style>
@stop
