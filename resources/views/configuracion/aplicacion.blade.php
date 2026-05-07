@extends('adminlte::page')

@section('title', 'Configuración de Aplicación')

@section('content_header')
    <h1>Configuración de Aplicación</h1>
@stop

@section('content')
    <div class="card">
        <div class="card-body">
            @if (session('status'))
                <div class="alert alert-success">{{ session('status') }}</div>
            @endif

            <form method="POST" action="{{ route('configuracion.aplicacion.update') }}">
                @csrf
                @method('PUT')

                <div class="form-group">
                    <label>Última versión (latestVersion)</label>
                    <input type="text" name="latestVersion" class="form-control" value="{{ old('latestVersion', $settings['latestVersion']) }}" required>
                </div>

                <div class="form-group">
                    <label>Versión mínima (minimumVersion)</label>
                    <input type="text" name="minimumVersion" class="form-control" value="{{ old('minimumVersion', $settings['minimumVersion']) }}" required>
                </div>

                <div class="form-group">
                    <label>URL de descarga (downloadUrl)</label>
                    <input type="url" name="downloadUrl" class="form-control" value="{{ old('downloadUrl', $settings['downloadUrl']) }}">
                </div>

                <div class="form-group">
                    <label>Título del aviso</label>
                    <input type="text" name="title" class="form-control" value="{{ old('title', $settings['title']) }}">
                </div>

                <div class="form-group">
                    <label>Mensaje del aviso</label>
                    <textarea name="message" class="form-control" rows="3">{{ old('message', $settings['message']) }}</textarea>
                </div>

                <div class="form-group form-check">
                    <input type="hidden" name="forceUpdate" value="0">
                    <input type="checkbox" class="form-check-input" id="forceUpdate" name="forceUpdate" value="1" {{ old('forceUpdate', $settings['forceUpdate']) ? 'checked' : '' }}>
                    <label class="form-check-label" for="forceUpdate">Forzar actualización</label>
                </div>

                <button type="submit" class="btn btn-primary">Guardar</button>
            </form>
        </div>
    </div>
@stop
