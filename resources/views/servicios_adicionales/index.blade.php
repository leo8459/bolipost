@extends('adminlte::page')

@section('title', 'Habilitar servicio adicional')

@section('content_header')
    <h1>Habilitar servicio adicional</h1>
@stop

@section('content')
    @if (session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger" role="alert">{{ $errors->first() }}</div>
    @endif
    <div class="card card-outline card-primary">
        <div class="card-header"><h2 class="card-title">Servicio de ida y vuelta por empresa</h2></div>
        <div class="card-body">
            <p>Selecciona qué empresas pueden solicitar una segunda guía de regreso. El cambio se aplica a todos los usuarios de la empresa para sus nuevos envíos.</p>
            <form method="GET" action="{{ route('servicios-adicionales.index') }}" class="mb-4">
                <div class="form-row align-items-end">
                    <div class="form-group col-md-6">
                        <label for="empresa-search">Buscar empresa</label>
                        <input id="empresa-search" class="form-control" name="q" value="{{ $search }}" placeholder="Nombre, sigla o código de cliente" maxlength="255">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="empresa-estado">Estado del servicio</label>
                        <select id="empresa-estado" name="estado" class="form-control">
                            <option value="">Todas las empresas</option>
                            <option value="habilitado" @selected($estado === 'habilitado')>Habilitado</option>
                            <option value="deshabilitado" @selected($estado === 'deshabilitado')>Deshabilitado</option>
                        </select>
                    </div>
                    <div class="form-group col-md-3">
                        <button class="btn btn-primary" type="submit">Buscar</button>
                        <a class="btn btn-default" href="{{ route('servicios-adicionales.index') }}">Limpiar</a>
                    </div>
                </div>
            </form>
            <p class="text-muted">{{ $empresas->total() }} empresas encontradas</p>
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead><tr><th>Empresa</th><th>Sigla</th><th>Código de cliente</th><th>Ida y vuelta</th><th>Acción</th></tr></thead>
                    <tbody>
                    @forelse ($empresas as $empresa)
                        <tr>
                            <td>{{ $empresa->nombre }}</td>
                            <td>{{ $empresa->sigla }}</td>
                            <td>{{ $empresa->codigo_cliente }}</td>
                            <td><span class="badge badge-{{ $empresa->servicio_ida_vuelta ? 'success' : 'secondary' }}">{{ $empresa->servicio_ida_vuelta ? 'Habilitado' : 'Deshabilitado' }}</span></td>
                            <td>
                                @can('feature.servicios-adicionales.edit')
                                    <form method="POST" action="{{ route('servicios-adicionales.update', $empresa) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="servicio_ida_vuelta" value="{{ $empresa->servicio_ida_vuelta ? '0' : '1' }}">
                                        <button type="submit" class="btn btn-sm btn-{{ $empresa->servicio_ida_vuelta ? 'outline-danger' : 'success' }}" aria-label="{{ $empresa->servicio_ida_vuelta ? 'Deshabilitar' : 'Habilitar' }} ida y vuelta para {{ $empresa->nombre }}">{{ $empresa->servicio_ida_vuelta ? 'Deshabilitar' : 'Habilitar' }}</button>
                                    </form>
                                @else
                                    <span class="text-muted">Solo lectura</span>
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center">No se encontraron empresas.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $empresas->links() }}
        </div>
    </div>
@stop
