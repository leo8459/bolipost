@extends('adminlte::page')
@section('title', 'Importar Tarifario Tiktoker')
@section('template_title')
    Importar Tarifario Tiktoker
@endsection

@section('content')
    <section class="content container-fluid">
        <div class="row">
            <div class="col-md-12">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="card card-default">
                    <div class="card-header">
                        <span class="card-title">Importacion Masiva de Tarifario Tiktoker (Excel)</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <a href="{{ route('tarifario-tiktoker.template-excel') }}" class="btn btn-outline-primary btn-sm">
                                Descargar Plantilla Excel
                            </a>
                        </div>

                        <form method="POST" action="{{ route('tarifario-tiktoker.import') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="archivo">Archivo Excel</label>
                                <input type="file" id="archivo" name="archivo" class="form-control @error('archivo') is-invalid @enderror" accept=".xlsx,.xls" required>
                                @error('archivo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="alert alert-info">
                                <p class="mb-2"><strong>Columnas requeridas en el Excel (fila 1):</strong></p>
                                <code>{{ implode(', ', $columnas) }}</code>
                                <p class="mt-2 mb-2">
                                    La plantilla incluye las hojas <strong>Origenes</strong> y <strong>Destinos</strong>
                                    para que uses los nombres correctos de los departamentos.
                                </p>
                                <p class="mt-2 mb-2">
                                    <strong>servicio_extra</strong> es opcional y debe coincidir con un nombre de la hoja <strong>ServiciosExtras</strong>.
                                </p>
                                <p class="mb-0">
                                    <strong>tiempo_entrega</strong> se importa en horas.
                                </p>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('tarifario-tiktoker.index') }}" class="btn btn-secondary">Volver</a>
                                <button type="submit" class="btn btn-primary">Importar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection
