@extends('adminlte::page')

@section('title', 'Importar empresas')
@section('template_title')
    Importar empresas
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                        <h3 class="card-title mb-0">Importar empresas por Excel</h3>
                        <a href="{{ route('empresas.template-excel') }}" class="btn btn-outline-primary btn-sm">
                            Descargar plantilla
                        </a>
                    </div>
                    <div class="card-body">
                        @if (session('success'))
                            <div class="alert alert-success">{{ session('success') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0 pl-3">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('empresas.import') }}" enctype="multipart/form-data">
                            @csrf

                            <div class="form-group">
                                <label for="archivo">Archivo Excel</label>
                                <input
                                    type="file"
                                    class="form-control @error('archivo') is-invalid @enderror"
                                    id="archivo"
                                    name="archivo"
                                    accept=".xlsx,.xls"
                                    required
                                >
                                <small class="form-text text-muted">
                                    La plantilla usa estas columnas: <strong>nombre</strong>, <strong>sigla</strong> y <strong>codigo_cliente</strong>.
                                </small>
                            </div>

                            <div class="alert alert-info mb-4">
                                Si el <strong>codigo_cliente</strong> ya existe, la importacion actualiza la empresa.
                                Si no existe, crea un nuevo registro.
                            </div>

                            <div class="d-flex justify-content-end gap-2">
                                <a href="{{ route('empresas.index') }}" class="btn btn-secondary">Volver</a>
                                <button type="submit" class="btn btn-primary">Importar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
