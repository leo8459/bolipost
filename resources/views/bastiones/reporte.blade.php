@extends('adminlte::page')

@section('title', 'Reporte de Bastión')

@section('content_header')
    <h1>Reporte de Bastión</h1>
    <p class="text-muted mb-0">{{ $source['archivo'] }} · Se conservan las filas repetidas del Excel. Los estados e imágenes se consultan en el sistema.</p>
@endsection

@section('content')
    <div class="btn-group mb-3" role="group" aria-label="Mes del reporte">
        @foreach(\App\Services\BastionReportService::MONTHS as $key => $label)
            <a class="btn {{ $month === $key ? 'btn-primary' : 'btn-outline-primary' }}" href="{{ route('bastiones.reporte', ['mes' => $key]) }}" @if($month === $key) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </div>
    <div class="row">
        @foreach(['filas' => 'Filas del Excel', 'codigos' => 'Códigos únicos', 'entregados' => 'Filas entregadas', 'sin_registro' => 'Filas sin registro'] as $key => $label)
            <div class="col-md-3 col-6"><div class="small-box bg-white border shadow-sm"><div class="inner"><h3>{{ $totals[$key] }}</h3><p>{{ $label }}</p></div></div></div>
        @endforeach
    </div>
    <div class="card shadow-sm">
        <div class="card-header">
            <div class="mb-3">
                <a class="btn btn-success" href="{{ route('bastiones.reporte.excel', request()->only('buscar', 'hoja', 'estado')) }}"><i class="fas fa-file-excel mr-1"></i> Generar Excel</a>
                <small class="text-muted ml-2">Un solo Excel con las hojas Abril y Mayo. Los filtros aplicados se usan en ambas hojas. Incluye enlaces a las imágenes de entrega.</small>
            </div>
            <form method="GET" action="{{ route('bastiones.reporte') }}" class="row align-items-end">
                <input type="hidden" name="mes" value="{{ $month }}">
                <div class="col-md-4 mb-2"><label for="buscar">Código o destinatario</label><input class="form-control" id="buscar" name="buscar" value="{{ $search }}" placeholder="Buscar paquete"></div>
                <div class="col-md-3 mb-2"><label for="hoja">Hoja del Excel</label><select class="form-control" id="hoja" name="hoja"><option value="">Todas</option>@foreach($sheets as $name)<option value="{{ $name }}" @selected($sheet === $name)>{{ $name }}</option>@endforeach</select></div>
                <div class="col-md-3 mb-2"><label for="estado">Estado</label><select class="form-control" id="estado" name="estado"><option value="">Todos</option>@foreach(['entregado' => 'Entregado', 'pendiente' => 'Sin entrega registrada', 'sin_registro' => 'Sin registro en el sistema'] as $key => $label)<option value="{{ $key }}" @selected($status === $key)>{{ $label }}</option>@endforeach</select></div>
                <div class="col-md-2 mb-2"><button class="btn btn-warning" type="submit">Buscar</button> <a class="btn btn-outline-secondary" href="{{ route('bastiones.reporte', ['mes' => $month]) }}" aria-label="Limpiar filtros"><i class="fas fa-times"></i></a></div>
            </form>
        </div>
        <div class="card-body py-2 text-muted">{{ $paquetes->total() }} filas encontradas. Historial ordenado por fecha.</div>
        <div class="table-responsive"><table class="table table-hover mb-0">
            <thead class="thead-light"><tr><th>Hoja / fila</th><th>Código</th><th>Fecha del Excel</th><th>Ruta</th><th>Destinatario</th><th>Estados por los que pasó</th><th>Imagen de entrega</th></tr></thead>
            <tbody>
                @forelse($paquetes as $paquete)
                    <tr>
                        <td>{{ $paquete['hoja'] }} <small class="d-block text-muted">Fila {{ $paquete['fila'] }}</small></td>
                        <td class="font-weight-bold text-nowrap">{{ $paquete['codigo'] }}</td>
                        <td class="text-nowrap">{{ $paquete['fecha'] ?: '—' }}</td>
                        <td>{{ $paquete['origen'] ?: '—' }} → {{ $paquete['destino'] ?: '—' }}</td>
                        <td>{{ $paquete['destinatario'] ?: '—' }}</td>
                        <td style="min-width:260px;max-width:440px">
                            @foreach($paquete['historial'] as $event)
                                <div class="mb-1"><small class="text-muted">{{ $event['fecha'] ? \Illuminate\Support\Carbon::parse($event['fecha'])->format('d/m/Y H:i') : 'Sin fecha' }}</small> · {{ $event['nombre'] }}</div>
                            @endforeach
                            @if($paquete['estado_actual'])<div class="mt-2"><span class="badge {{ $paquete['entregado'] ? 'badge-success' : 'badge-info' }}">Estado actual: {{ $paquete['estado_actual'] }}</span></div>@endif
                            @if(!$paquete['encontrado'])<span class="text-muted">Sin registro en el sistema</span>@elseif(empty($paquete['historial']))<small class="text-muted d-block">Sin historial de eventos</small>@endif
                        </td>
                        <td>
                            @if($paquete['imagen'])
                                <a href="{{ route('bastiones.reporte.imagen', $paquete['codigo']) }}" target="_blank" rel="noopener" aria-label="Ver imagen de entrega de {{ $paquete['codigo'] }}"><img src="{{ route('bastiones.reporte.imagen', $paquete['codigo']) }}" alt="Comprobante de entrega de {{ $paquete['codigo'] }}" loading="lazy" style="width:110px;height:85px;object-fit:contain" class="img-thumbnail"><small class="d-block">Ver imagen</small></a>
                            @elseif($paquete['entregado'])<span class="text-muted">Entregado, sin imagen registrada</span>
                            @else<span class="text-muted">Sin entrega registrada</span>@endif
                        </td>
                    </tr>
                @empty<tr><td colspan="7" class="text-center text-muted py-5">No se encontraron paquetes con estos filtros.</td></tr>@endforelse
            </tbody>
        </table></div>
        @if($paquetes->hasPages())<div class="card-footer">{{ $paquetes->links() }}</div>@endif
    </div>
@endsection
