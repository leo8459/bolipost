@extends('adminlte::page')

@section('title', 'Entregas')

@section('content_header')
    <div class="d-flex flex-wrap align-items-center justify-content-between">
        <h1 class="m-0">Entregas</h1>
        <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">Volver al dashboard</a>
    </div>
@stop

@section('content')
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" action="{{ route('entregas.index') }}" class="row">
                <div class="col-md-3">
                    <label class="mb-1">Rango</label>
                    <select class="form-control" name="range">
                        @php
                            $rangeValue = old('range', request('range', $rangoKey ?? 'all'));
                        @endphp
                        <option value="all" @selected($rangeValue === 'all')>Todo el historial</option>
                        <option value="today" @selected($rangeValue === 'today')>Hoy</option>
                        <option value="7d" @selected($rangeValue === '7d')>Ultimos 7 dias</option>
                        <option value="month" @selected($rangeValue === 'month')>Mes actual</option>
                        <option value="custom" @selected($rangeValue === 'custom')>Personalizado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="mb-1">Desde</label>
                    <input type="date" class="form-control" name="from" value="{{ request('from', $rangoDesde ?? '') }}">
                </div>
                <div class="col-md-3">
                    <label class="mb-1">Hasta</label>
                    <input type="date" class="form-control" name="to" value="{{ request('to', $rangoHasta ?? '') }}">
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary mr-2">Filtrar</button>
                    <a href="{{ route('entregas.index') }}" class="btn btn-outline-secondary">Limpiar</a>
                </div>

                <div class="col-12 mt-3">
                    <label class="mb-2 d-block">Modulos</label>
                    <div class="d-flex flex-wrap" style="gap: .65rem 1rem;">
                        @foreach($modulosDisponibles as $modKey => $modConfig)
                            <label class="mb-0" style="font-weight:500;">
                                <input type="checkbox" name="modules[]" value="{{ $modKey }}" @checked(in_array($modKey, $modulosSeleccionados, true))>
                                {{ $modConfig['label'] }}
                            </label>
                        @endforeach
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <strong>Todos los entregadores</strong>
            <span class="text-muted ml-2">({{ $rangoLabel ?? 'Todo el historial' }})</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th class="text-right">Total entregas</th>
                            <th class="text-right">EMS</th>
                            <th class="text-right">Contratos</th>
                            <th class="text-right">Certificados</th>
                            <th class="text-right">Ordinarios</th>
                            <th>Servicio mas entregado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($entregadores as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                <td class="text-right">{{ number_format((int) $item->total_entregados) }}</td>
                                <td class="text-right">{{ number_format((int) $item->ems) }}</td>
                                <td class="text-right">{{ number_format((int) $item->contrato) }}</td>
                                <td class="text-right">{{ number_format((int) $item->certi) }}</td>
                                <td class="text-right">{{ number_format((int) $item->ordi) }}</td>
                                <td>
                                    <strong>{{ $item->servicio_mas_entregado }}</strong>
                                    <span class="text-muted">({{ number_format((int) $item->servicio_mas_entregado_total) }})</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">No hay entregas para el filtro seleccionado.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@stop
