@extends('adminlte::page')
@section('title', 'Importar Tarifario')
@section('template_title')
    Importar Tarifario
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
                        <span class="card-title">Importacion Masiva de Tarifario (Excel)</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <a href="{{ route('tarifario.template-excel') }}" class="btn btn-outline-primary btn-sm">
                                Descargar Plantilla Excel
                            </a>
                            <a href="{{ route('tarifario.template-mass-excel', ['servicio' => 'EMS_NACIONAL']) }}" class="btn btn-outline-success btn-sm ml-2">
                                Descargar Plantilla Masiva EMS_NACIONAL
                            </a>
                        </div>

                        <form method="POST" action="{{ route('tarifario.import') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-group mb-3">
                                <label for="archivo">Archivo Excel</label>
                                <input
                                    type="file"
                                    id="archivo"
                                    name="archivo"
                                    class="form-control @error('archivo') is-invalid @enderror"
                                    accept=".xlsx,.xls"
                                    required
                                >
                                @error('archivo')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="alert alert-info">
                                <p class="mb-2"><strong>Columnas requeridas en el Excel (fila 1):</strong></p>
                                <code>{{ implode(', ', $columnas) }}</code>
                                <p class="mt-2 mb-2">
                                    La plantilla ya trae cargados los rangos de peso desde <strong>0.001 kg</strong> hasta
                                    <strong>20.000 kg</strong>.
                                </p>
                                <p class="mt-2 mb-2">
                                    Las columnas <strong>servicio</strong>, <strong>origen</strong> y <strong>destino</strong>
                                    deben coincidir con los catalogos del sistema.
                                </p>
                                <p class="mt-2 mb-0">
                                    Si una combinacion de servicio, origen, destino y peso ya existe, la importacion
                                    actualiza <strong>precio</strong> y <strong>observacion</strong>.
                                </p>
                                <hr>
                                <p class="mb-0">
                                    Si quieres evitar repetir LA PAZ, COCHABAMBA, BENI, etc. muchas veces,
                                    usa la <strong>Plantilla Masiva EMS_NACIONAL</strong>. Esa hoja ya sale con
                                    todas las combinaciones de departamentos y pesos; solo llenas <strong>precio</strong>.
                                </p>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="{{ route('tarifario.index') }}" class="btn btn-secondary">Volver</a>
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
