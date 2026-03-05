@extends('adminlte::page')

@section('title', 'Reportes Ejecutivos')

@section('content_header')
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center">
        <div>
            <h1 class="mb-0">Centro de Reportes</h1>
            <small class="text-muted">Consulta operativa y generacion de reportes PDF</small>
        </div>
        <div class="mt-2 mt-md-0 text-md-right">
            <div class="text-muted mb-2">
                <strong>Rango:</strong> {{ $rangoLabel }}
            </div>
            <a href="{{ route('reportes.export.pdf', request()->query()) }}" class="btn btn-danger btn-sm">
                Exportar PDF Ejecutivo
            </a>
        </div>
    </div>
@stop

@section('content')
    <div class="card card-filtro mb-3">
        <div class="card-header">
            <strong>Filtros de Reporte</strong>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('reportes.index') }}">
                <div class="row">
                    <div class="col-lg-6 mb-3">
                        <label class="d-block font-weight-bold">Modulos incluidos</label>
                        <div class="d-flex flex-wrap">
                            @foreach($modulosDisponibles as $key => $modulo)
                                <div class="custom-control custom-checkbox mr-4 mb-2">
                                    <input
                                        class="custom-control-input"
                                        type="checkbox"
                                        id="modulo_{{ $key }}"
                                        name="modules[]"
                                        value="{{ $key }}"
                                        {{ in_array($key, $modulosSeleccionados, true) ? 'checked' : '' }}
                                    >
                                    <label class="custom-control-label" for="modulo_{{ $key }}">
                                        {{ $modulo['label'] }}
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="col-lg-4 mb-3">
                        <div class="row">
                            <div class="col-6">
                                <label for="from" class="font-weight-bold">Desde</label>
                                <input type="date" id="from" name="from" class="form-control" value="{{ $rangoDesde }}">
                            </div>
                            <div class="col-6">
                                <label for="to" class="font-weight-bold">Hasta</label>
                                <input type="date" id="to" name="to" class="form-control" value="{{ $rangoHasta }}">
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-2 mb-3">
                        <label for="group" class="font-weight-bold">Agrupar por</label>
                        <select id="group" name="group" class="form-control">
                            <option value="day" {{ $agrupacion === 'day' ? 'selected' : '' }}>Dia</option>
                            <option value="week" {{ $agrupacion === 'week' ? 'selected' : '' }}>Semana</option>
                            <option value="month" {{ $agrupacion === 'month' ? 'selected' : '' }}>Mes</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex flex-wrap align-items-center">
                    <button type="submit" class="btn btn-primary mr-2 mb-2">Aplicar filtros</button>
                </div>
            </form>
        </div>
    </div>

    @include('footer')
@stop

@section('css')
    <style>
        .card-filtro {
            border-top: 3px solid #20539a;
        }
    </style>
@stop
