@extends('adminlte::page')

@section('title', 'Respaldos')

@section('content_header')
    <h1>Respaldos del Sistema</h1>
@endsection

@section('content')
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Generar respaldos desde el servidor</h3>
        </div>
        <div class="card-body d-flex flex-wrap" style="gap: 10px;">
            <form action="{{ route('backups.database') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-database mr-1"></i> Respaldo Base de Datos
                </button>
            </form>

            <form action="{{ route('backups.system') }}" method="POST">
                @csrf
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-server mr-1"></i> Respaldo del Sistema
                </button>
            </form>
        </div>
    </div>

    <div class="card mt-3">
        <div class="card-header">
            <h3 class="card-title">Archivos generados</h3>
        </div>
        <div class="card-body p-0">
            <table class="table table-striped mb-0">
                <thead>
                    <tr>
                        <th>Archivo</th>
                        <th>Tipo</th>
                        <th>Tamaño</th>
                        <th>Fecha</th>
                        <th style="width: 140px;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($files as $file)
                        <tr>
                            <td>{{ $file['name'] }}</td>
                            <td>{{ $file['type'] }}</td>
                            <td>{{ $file['size'] }}</td>
                            <td>{{ $file['created_at'] }}</td>
                            <td>
                                <a href="{{ route('backups.download', $file['name']) }}" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-download"></i> Descargar
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No hay respaldos generados todavía.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
