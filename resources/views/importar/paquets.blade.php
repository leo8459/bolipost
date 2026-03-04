@extends('adminlte::page')
@section('title', 'Importar Paquets')
@section('template_title')
    Importar Paquets
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
                        <span class="card-title">IMPORTAR PAQUETS</span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <a href="{{ route('importar.paquets.template-excel') }}" class="btn btn-outline-primary btn-sm">
                                Descargar Plantilla Excel
                            </a>
                        </div>

                        <form method="POST" action="{{ route('importar.paquets.store') }}" enctype="multipart/form-data">
                            @csrf
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="ciudad">Ciudad (para todos los registros)</label>
                                    <select
                                        id="ciudad"
                                        name="ciudad"
                                        class="form-control @error('ciudad') is-invalid @enderror"
                                        required
                                    >
                                        <option value="">Seleccione</option>
                                        @foreach($ciudades as $ciudad)
                                            <option value="{{ $ciudad }}" @selected(old('ciudad', $ciudadPorDefecto) == $ciudad)>
                                                {{ $ciudad }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('ciudad')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group col-md-4">
                                    <label for="fk_ventanilla">Ventanilla</label>
                                    <select
                                        id="fk_ventanilla"
                                        name="fk_ventanilla"
                                        class="form-control @error('fk_ventanilla') is-invalid @enderror"
                                        required
                                    >
                                        <option value="">Seleccione</option>
                                        @foreach($ventanillas as $ventanilla)
                                            <option value="{{ $ventanilla->id }}" @selected(old('fk_ventanilla') == $ventanilla->id)>
                                                {{ $ventanilla->nombre_ventanilla }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('fk_ventanilla')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="form-group col-md-4">
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
                            </div>

                            <div class="alert alert-info">
                                <p class="mb-2"><strong>Columnas requeridas en la fila 1 del Excel:</strong></p>
                                <code>{{ implode(', ', $columnas) }}</code>
                            </div>

                            <div class="d-flex justify-content-end">
                                <button type="submit" class="btn btn-primary">Importar</button>
                            </div>
                        </form>

                        @if(session('success'))
                            <div class="alert alert-success mt-3 mb-0">
                                {{ session('success') }}
                            </div>
                        @endif

                        @if(session('warning'))
                            <div class="alert alert-warning mt-3">
                                {{ session('warning') }}
                            </div>
                        @endif

                        @if(session('import_errors'))
                            <div class="alert alert-danger mt-3 mb-0">
                                <p class="mb-2"><strong>Detalle de filas con error:</strong></p>
                                <ul class="mb-0">
                                    @foreach(session('import_errors') as $importError)
                                        <li>{{ $importError }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </section>
    @include('footer')
@endsection

